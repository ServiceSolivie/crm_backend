<?php

namespace App\Services;

use App\Enums\ActivityEventEnum;
use App\Enums\LeadStatusEnum;
use App\Enums\PaymentRecordStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Lead;
use App\Models\User;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\PaymentSessionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Payments of a lead and its contract total. Payments come only from
 * Hyperswitch: payment links are created by PaymentSessionService and
 * PaymentSyncService records each attempt when it syncs the status.
 * Payments entered by hand in the past stay in the list, read-only.
 *
 * Database access goes through the repositories; this class only holds
 * the business rules.
 */
class PaymentService
{
    /** Leads that left the pipeline: no payment can be requested on them */
    public const CLOSED_STATUSES = [
        LeadStatusEnum::PERDU,
        LeadStatusEnum::PAS_INTERESSE,
        LeadStatusEnum::MAUVAIS_NUMERO,
        LeadStatusEnum::LEAD_INVALIDE,
    ];

    public function __construct(
        protected PaymentRepositoryInterface $payments,
        protected PaymentSessionRepositoryInterface $sessions,
        protected LeadRepositoryInterface $leads,
        protected LeadService $leadService,
        protected ActivityLogger $activity,
    ) {}

    public function listForLead(Lead $lead, int $perPage = 15): LengthAwarePaginator
    {
        return $this->payments->paginateForLead($lead->id, $perPage);
    }

    /**
     * Refuse leads that left the pipeline (lost, not interested, invalid…).
     */
    public function ensureLeadIsOpen(Lead $lead): void
    {
        if (in_array($lead->status, self::CLOSED_STATUSES, true)) {
            throw ValidationException::withMessages([
                'lead' => 'Impossible de demander un paiement sur un lead perdu ou invalide.',
            ]);
        }
    }

    /**
     * Contract total minus the money already received or pending: what can
     * still be asked from the client.
     */
    public function remainingToCollect(Lead $lead): string
    {
        return bcsub((string) $lead->expected_revenue, $this->committedAmount($lead), 2);
    }

    /**
     * Change the contract total, e.g. to ask an additional payment on a
     * lead already fully paid when the contract grew. It can't go below
     * what the client already paid or is paying (received + pending +
     * the link waiting for the client). The change is kept as a lead note.
     */
    public function updateContractTotal(Lead $lead, User $user, string|float|int $total, string $reason): Lead
    {
        return DB::transaction(function () use ($lead, $user, $total, $reason) {
            /** @var Lead $lead */
            $lead = $this->leads->findForUpdate($lead->id);

            $total = number_format((float) $total, 2, '.', '');
            $previous = $lead->expected_revenue !== null ? (string) $lead->expected_revenue : null;

            if (bccomp($total, '0', 2) <= 0) {
                throw ValidationException::withMessages(['total' => 'Le montant total doit être supérieur à 0.']);
            }

            if ($previous !== null && bccomp($total, $previous, 2) === 0) {
                throw ValidationException::withMessages(['total' => 'Le montant total est déjà de '.$this->euros($total).'.']);
            }

            $minimum = $this->minimumContractTotal($lead);
            if (bccomp($total, $minimum, 2) < 0) {
                throw ValidationException::withMessages([
                    'total' => 'Le montant total ne peut pas être inférieur à '.$this->euros($minimum).' (paiements reçus, en cours et lien en attente).',
                ]);
            }

            $lead->update(['expected_revenue' => $total]);
            $this->recalculatePaymentStatus($lead);

            $this->leadService->addNote($lead, $user, sprintf(
                'Montant total du contrat modifié : %s → %s. Motif : %s',
                $previous !== null ? $this->euros($previous) : 'non défini',
                $this->euros($total),
                $reason,
            ));

            $this->activity->contractTotal($lead, ActivityEventEnum::PAYMENT_TOTAL_CHANGED, sprintf(
                '%s → %s. Motif : %s',
                $previous !== null ? $this->euros($previous) : 'non défini',
                $this->euros($total),
                $reason,
            ), ['previous_total' => $previous, 'new_total' => $total, 'reason' => $reason], $user);

            return $lead;
        });
    }

    /**
     * Lead payment status from its payments:
     *   received ≥ total                 → PAYE
     *   0 < received < total             → PARTIELLEMENT_PAYE
     *   nothing received, some pending   → EN_ATTENTE
     *   nothing received, some refunded  → REMBOURSE (older manual payments)
     *   otherwise                        → NON_PAYE
     */
    public function recalculatePaymentStatus(Lead $lead): void
    {
        $lead->refresh();
        $received = $this->payments->sumForLead($lead->id, [PaymentRecordStatusEnum::REUSSI]);

        if ($lead->expected_revenue !== null && bccomp($received, (string) $lead->expected_revenue, 2) >= 0 && bccomp($received, '0', 2) > 0) {
            $status = PaymentStatusEnum::PAYE;
        } elseif (bccomp($received, '0', 2) > 0) {
            $status = PaymentStatusEnum::PARTIELLEMENT_PAYE;
        } elseif ($this->payments->existsForLead($lead->id, PaymentRecordStatusEnum::EN_ATTENTE)) {
            $status = PaymentStatusEnum::EN_ATTENTE;
        } elseif ($this->payments->existsForLead($lead->id, PaymentRecordStatusEnum::REMBOURSE)) {
            $status = PaymentStatusEnum::REMBOURSE;
        } else {
            $status = PaymentStatusEnum::NON_PAYE;
        }

        $lead->update(['payment_status' => $status->value]);
    }

    /**
     * Money received or being paid (payments REUSSI + EN_ATTENTE).
     */
    protected function committedAmount(Lead $lead): string
    {
        return $this->payments->sumForLead($lead->id, [PaymentRecordStatusEnum::REUSSI, PaymentRecordStatusEnum::EN_ATTENTE]);
    }

    /**
     * Lowest possible contract total: what is committed, plus the pending
     * link while the client hasn't paid it yet (once paid or processing, its
     * payment is already in the committed amount).
     */
    protected function minimumContractTotal(Lead $lead): string
    {
        $minimum = $this->committedAmount($lead);

        $open = $this->sessions->findOpenForLead($lead->id);
        if ($open && (! $open->hyperswitch_payment_id || ! $this->payments->findByExternalId($open->hyperswitch_payment_id))) {
            $minimum = bcadd($minimum, (string) $open->amount, 2);
        }

        return $minimum;
    }

    protected function euros(string $amount): string
    {
        return number_format((float) $amount, 2, ',', ' ').' €';
    }
}
