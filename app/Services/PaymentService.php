<?php

namespace App\Services;

use App\Enums\LeadStatusEnum;
use App\Enums\PaymentRecordStatusEnum;
use App\Enums\PaymentSourceEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payments recorded on a lead. Today they are entered by hand (source
 * MANUEL); later the Hyperswitch webhook will create / update them through
 * recordProviderEvent() with the same statuses.
 *
 * Payments are independent of the pipeline: they can be recorded before or
 * after the DVC is signed, and before Validé — only lost leads are refused.
 */
class PaymentService
{
    /** Leads that left the pipeline: no payment can be recorded on them */
    public const CLOSED_STATUSES = [
        LeadStatusEnum::PERDU,
        LeadStatusEnum::PAS_INTERESSE,
        LeadStatusEnum::MAUVAIS_NUMERO,
        LeadStatusEnum::LEAD_INVALIDE,
    ];

    public function listForLead(Lead $lead, int $perPage = 15): LengthAwarePaginator
    {
        return $lead->payments()
            ->with(['creator', 'statusChanger'])
            ->latest('payment_date')
            ->latest('id')
            ->paginate($perPage);
    }

    public function createPayment(Lead $lead, array $data, User $creator): Payment
    {
        return DB::transaction(function () use ($lead, $data, $creator) {
            $lead = Lead::lockForUpdate()->find($lead->id);

            if (in_array($lead->status, self::CLOSED_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'lead' => 'Impossible d\'enregistrer un paiement sur un lead perdu ou invalide.',
                ]);
            }

            // The contract total is given with the first payment
            if (isset($data['expected_revenue'])) {
                if ($lead->expected_revenue !== null && bccomp((string) $data['expected_revenue'], (string) $lead->expected_revenue, 2) !== 0 && $lead->payments()->exists()) {
                    throw ValidationException::withMessages([
                        'expected_revenue' => 'Le montant total est déjà fixé pour ce lead.',
                    ]);
                }
                $lead->expected_revenue = $data['expected_revenue'];
                $lead->save();
            }

            if ($lead->expected_revenue === null) {
                throw ValidationException::withMessages([
                    'expected_revenue' => 'Indiquez le montant total du contrat avec le premier paiement.',
                ]);
            }

            $status = PaymentRecordStatusEnum::from($data['status'] ?? PaymentRecordStatusEnum::REUSSI->value);

            // Received + pending money can never exceed the contract total
            $committed = $this->sumByStatus($lead, [PaymentRecordStatusEnum::REUSSI, PaymentRecordStatusEnum::EN_ATTENTE]);
            $remaining = bcsub((string) $lead->expected_revenue, $committed, 2);

            if (bccomp((string) $data['amount'], $remaining, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => "Le montant dépasse le solde restant ({$remaining} €).",
                ]);
            }

            unset($data['expected_revenue']);

            $payment = $lead->payments()->create([
                ...$data,
                'status' => $status,
                'source' => PaymentSourceEnum::MANUEL,
                'created_by' => $creator->id,
            ]);

            $this->recalculatePaymentStatus($lead);

            return $payment->load(['creator', 'statusChanger']);
        });
    }

    /**
     * Move a payment to another status by hand (e.g. pending → received,
     * received → refunded). Only the transitions of
     * PaymentRecordStatusEnum::allowedTransitions() are accepted.
     */
    public function changeStatus(Payment $payment, PaymentRecordStatusEnum $status, User $user, ?string $reason = null): Payment
    {
        return DB::transaction(function () use ($payment, $status, $user, $reason) {
            $payment = Payment::lockForUpdate()->find($payment->id);

            if (! in_array($status, $payment->status->allowedTransitions(), true)) {
                throw ValidationException::withMessages([
                    'status' => "Un paiement « {$payment->status->label()} » ne peut pas passer à « {$status->label()} ».",
                ]);
            }

            $payment->update([
                'status' => $status,
                'failure_reason' => $reason ?? $payment->failure_reason,
                'status_changed_at' => now(),
                'status_changed_by' => $user->id,
            ]);

            $this->recalculatePaymentStatus($payment->lead);

            return $payment->load(['creator', 'statusChanger']);
        });
    }

    public function deletePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $lead = $payment->lead;
            $payment->delete();
            $this->recalculatePaymentStatus($lead);
        });
    }

    /**
     * Entry point for the future Hyperswitch webhook (not wired yet).
     * It will find the payment by external_id (or create it with
     * source HYPERSWITCH), map the provider status onto
     * PaymentRecordStatusEnum, store the raw event in provider_payload and
     * call recalculatePaymentStatus() — the lead then shows the same
     * statuses as manual payments.
     */
    public function recordProviderEvent(array $event): void
    {
        throw new \LogicException('The Hyperswitch webhook is not implemented yet.');
    }

    /**
     * Lead payment status from its payments:
     *   received ≥ total                 → PAYE
     *   0 < received < total             → PARTIELLEMENT_PAYE
     *   nothing received, some pending   → EN_ATTENTE
     *   nothing received, some refunded  → REMBOURSE
     *   otherwise                        → NON_PAYE
     */
    public function recalculatePaymentStatus(Lead $lead): void
    {
        $lead->refresh();
        $received = $this->sumByStatus($lead, [PaymentRecordStatusEnum::REUSSI]);

        if ($lead->expected_revenue !== null && bccomp($received, (string) $lead->expected_revenue, 2) >= 0 && bccomp($received, '0', 2) > 0) {
            $status = PaymentStatusEnum::PAYE;
        } elseif (bccomp($received, '0', 2) > 0) {
            $status = PaymentStatusEnum::PARTIELLEMENT_PAYE;
        } elseif ($lead->payments()->where('status', PaymentRecordStatusEnum::EN_ATTENTE->value)->exists()) {
            $status = PaymentStatusEnum::EN_ATTENTE;
        } elseif ($lead->payments()->where('status', PaymentRecordStatusEnum::REMBOURSE->value)->exists()) {
            $status = PaymentStatusEnum::REMBOURSE;
        } else {
            $status = PaymentStatusEnum::NON_PAYE;
        }

        $lead->update(['payment_status' => $status->value]);
    }

    /**
     * @param  array<int, PaymentRecordStatusEnum>  $statuses
     */
    protected function sumByStatus(Lead $lead, array $statuses): string
    {
        return (string) ($lead->payments()
            ->whereIn('status', array_map(fn ($s) => $s->value, $statuses))
            ->sum('amount') ?: '0');
    }
}
