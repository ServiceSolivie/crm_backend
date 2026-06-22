<?php

namespace App\Services;

use App\Enums\LeadStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function listForLead(Lead $lead, int $perPage = 15): LengthAwarePaginator
    {
        return $lead->payments()
            ->with('creator')
            ->latest('payment_date')
            ->paginate($perPage);
    }

    public function createPayment(Lead $lead, array $data, User $creator): Payment
    {
        return DB::transaction(function () use ($lead, $data, $creator) {
            $lead = Lead::lockForUpdate()->find($lead->id);

            if ($lead->status !== LeadStatusEnum::VALIDE) {
                throw ValidationException::withMessages([
                    'lead' => 'Les paiements ne peuvent être enregistrés que pour les leads validés.',
                ]);
            }

            if ($lead->expected_revenue === null) {
                throw ValidationException::withMessages([
                    'lead' => 'Le montant attendu n\'est pas défini pour ce lead.',
                ]);
            }

            $totalReceived = $lead->payments()->sum('amount');
            $remaining = bcsub($lead->expected_revenue, $totalReceived, 2);

            if (bccomp($data['amount'], $remaining, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => "Le montant dépasse le solde restant ({$remaining} €).",
                ]);
            }

            $payment = $lead->payments()->create([
                ...$data,
                'created_by' => $creator->id,
            ]);

            $this->recalculatePaymentStatus($lead);

            return $payment->load('creator');
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

    protected function recalculatePaymentStatus(Lead $lead): void
    {
        $totalReceived = $lead->payments()->sum('amount');

        if (bccomp($totalReceived, $lead->expected_revenue, 2) >= 0) {
            $status = PaymentStatusEnum::PAYE;
        } elseif (bccomp($totalReceived, '0', 2) > 0) {
            $status = PaymentStatusEnum::PARTIELLEMENT_PAYE;
        } else {
            $status = PaymentStatusEnum::NON_PAYE;
        }

        $lead->update(['payment_status' => $status->value]);
    }
}
