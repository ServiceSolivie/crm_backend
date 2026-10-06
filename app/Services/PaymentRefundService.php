<?php

namespace App\Services;

use App\Enums\PaymentRecordStatusEnum;
use App\Enums\PaymentRefundStatusEnum;
use App\Enums\PaymentSessionStatusEnum;
use App\Enums\PaymentSourceEnum;
use App\Events\PaymentSessionUpdated;
use App\Exceptions\ApiException;
use App\Exceptions\HyperswitchUncertainException;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentSession;
use App\Models\User;
use App\Notifications\PaymentRefundNotification;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Repositories\Contracts\PaymentRefundRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\PaymentSessionRepositoryInterface;
use App\Support\RefundSyncSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Refunds of a paid payment session, through Hyperswitch (integration doc
 * §8–9). One session = one Hyperswitch payment, always refunded in full:
 * Sogecommerce refuses partial refunds (IR_19).
 *
 * The refund row is saved with its CRM-chosen refund_id BEFORE calling
 * Hyperswitch (status A_VERIFIER), so an unclear answer is checked with
 * that id, never retried as a new refund. A pending refund (credit waiting
 * for the bank) is followed until final, never replaced by a second one.
 *
 * When the refund succeeds: the payment becomes REMBOURSE, the session
 * REMBOURSEE, and the lead's contract total drops by the refunded amount
 * (so what is left to collect doesn't change).
 */
class PaymentRefundService
{
    public function __construct(
        protected PaymentRefundRepositoryInterface $refunds,
        protected PaymentSessionRepositoryInterface $sessions,
        protected PaymentRepositoryInterface $payments,
        protected LeadRepositoryInterface $leads,
        protected PaymentService $paymentService,
        protected LeadService $leadService,
        protected HyperswitchClient $hyperswitch,
    ) {}

    /**
     * Refund a paid session in full. Returns the refund: REUSSI (cancelled
     * before settlement), EN_ATTENTE (credit waiting for the bank), or
     * A_VERIFIER (Hyperswitch gave no clear answer: it is checked later).
     *
     * @throws ValidationException|ApiException
     */
    public function create(PaymentSession $session, User $user, string $reason): PaymentRefund
    {
        // Fail fast on the CRM's own data, before asking Hyperswitch
        $this->ensureRefundable($session);
        $this->ensurePaidAtHyperswitch($session);

        $refund = DB::transaction(function () use ($session, $user, $reason) {
            /** @var PaymentSession $locked */
            $locked = $this->sessions->findForUpdate($session->id);
            $payment = $this->ensureRefundable($locked);

            return $this->refunds->create([
                'payment_session_id' => $locked->id,
                'payment_id' => $payment->id,
                'hyperswitch_refund_id' => HyperswitchClient::generateRefundId(),
                'amount' => $locked->amount,
                'currency' => $locked->currency,
                'status' => PaymentRefundStatusEnum::A_VERIFIER,
                'reason' => $reason,
                'next_sync_at' => now()->addSeconds(RefundSyncSchedule::CHECK_INTERVAL),
                'requested_by' => $user->id,
            ]);
        });

        try {
            $result = $this->hyperswitch->createRefund(
                refundId: $refund->hyperswitch_refund_id,
                paymentId: $session->hyperswitch_payment_id,
                amount: (string) $refund->amount,
                reason: $reason,
                metadata: ['crm_reference' => $session->reference, 'lead_id' => (string) $session->lead_id],
            );
        } catch (HyperswitchUncertainException $e) {
            // Stays A_VERIFIER: the scheduled check reads it with its refund_id
            report($e);

            return $refund->refresh();
        } catch (ApiException $e) {
            // Clear refusal (e.g. IR_19): nothing was created at Hyperswitch
            $code = is_array($e->getErrors()) ? ($e->getErrors()['code'] ?? null) : null;
            $refund->update([
                'status' => PaymentRefundStatusEnum::ECHOUE,
                'error_code' => $code !== null ? mb_substr((string) $code, 0, 50) : null,
                'error_message' => mb_substr($e->getMessage(), 0, 255),
                'next_sync_at' => null,
            ]);
            $this->broadcast($session);

            throw $e;
        }

        $outcome = DB::transaction(fn () => $this->apply($refund->id, [
            ...$result['payload'],
            'status' => $result['status'],
            'connector_refund_id' => $result['connector_refund_id'],
        ]));

        // The requester sees the result right away: no notification here
        $this->afterChange($outcome, notify: false);

        return $outcome['refund'];
    }

    /**
     * "Vérifier" button: fresh status of a pending refund. A waiting credit
     * is asked to Sogecommerce (force_sync), at most once a minute.
     *
     * @throws ValidationException|ApiException
     */
    public function verify(PaymentRefund $refund): PaymentRefund
    {
        if (! $refund->isPending()) {
            throw ValidationException::withMessages([
                'refund' => "Ce remboursement est « {$refund->status->label()} » : il n'y a rien à vérifier.",
            ]);
        }

        $recent = $refund->last_synced_at && $refund->last_synced_at->diffInSeconds(now(), true) < 60;
        $force = RefundSyncSchedule::shouldForce($refund->status) && ! $recent;

        return $this->sync($refund, $force);
    }

    /**
     * Scheduled job: follow the pending refunds that are due.
     *
     * @return int number of refunds synced
     */
    public function syncDue(int $limit = 50): int
    {
        $count = 0;

        foreach ($this->refunds->dueForSync($limit) as $refund) {
            try {
                $this->sync($refund, RefundSyncSchedule::shouldForce($refund->status));
                $count++;
            } catch (Throwable $e) {
                // Already rescheduled: go on with the others
                report($e);
            }
        }

        return $count;
    }

    /**
     * Ask Hyperswitch for the refund and apply the answer (idempotent).
     * Final statuses never move back.
     *
     * @throws ApiException|HyperswitchUncertainException when Hyperswitch
     *                                                    gives no usable answer (the refund is rescheduled first)
     */
    public function sync(PaymentRefund $refund, bool $force = false): PaymentRefund
    {
        if (! $refund->isPending()) {
            return $refund;
        }

        try {
            $body = $this->hyperswitch->retrieveRefund($refund->hyperswitch_refund_id, $force);
        } catch (HyperswitchUncertainException $e) {
            $this->reschedule($refund);

            throw $e;
        } catch (ApiException $e) {
            if ($e->getStatusCode() === 404 && $refund->status === PaymentRefundStatusEnum::A_VERIFIER) {
                // The unclear creation never reached Hyperswitch: nothing was refunded
                return $this->markNotCreated($refund);
            }
            $this->reschedule($refund);

            throw $e;
        }

        $outcome = DB::transaction(fn () => $this->apply($refund->id, $body));
        $this->afterChange($outcome, notify: true);

        return $outcome['refund'];
    }

    /* ── Applying an answer ──────────────────────────────────────────── */

    /**
     * Apply one Hyperswitch refund answer to the locked refund.
     *
     * @param  array<string, mixed>  $body  Hyperswitch refund (secrets removed)
     * @return array{refund: PaymentRefund, previous: PaymentRefundStatusEnum, changed: bool}
     */
    protected function apply(int $refundId, array $body): array
    {
        /** @var PaymentRefund $refund */
        $refund = $this->refunds->findForUpdate($refundId);
        $previous = $refund->status;

        // Another sync already closed it
        if (! $refund->isPending()) {
            return ['refund' => $refund, 'previous' => $previous, 'changed' => false];
        }

        $now = now();
        $attempts = $refund->sync_attempts + 1;
        $status = PaymentRefundStatusEnum::fromProvider($body['status'] ?? null) ?? $previous;

        $attributes = [
            'status' => $status,
            'provider_status' => $body['status'] ?? null,
            'provider_payload' => $body,
            'last_synced_at' => $now,
            'sync_attempts' => $attempts,
            ...array_filter(['connector_refund_id' => $body['connector_refund_id'] ?? null]),
            ...HyperswitchClient::errorOf($body),
        ];

        if ($status === PaymentRefundStatusEnum::REUSSI) {
            $attributes += ['refunded_at' => $now, 'next_sync_at' => null];
        } elseif ($status === PaymentRefundStatusEnum::ECHOUE) {
            $attributes += ['next_sync_at' => null];
        } else {
            $attributes += ['next_sync_at' => RefundSyncSchedule::nextCheckAt($status, $attempts, $refund->created_at, $now)];
        }

        $refund->update($attributes);

        if ($status === PaymentRefundStatusEnum::REUSSI) {
            $this->completeRefund($refund);
        }

        return ['refund' => $refund, 'previous' => $previous, 'changed' => $status !== $previous];
    }

    /**
     * The refund succeeded: the payment is refunded, the session closed,
     * and the contract total lowered by the refunded amount (kept as a
     * lead note). Runs inside apply()'s transaction.
     */
    protected function completeRefund(PaymentRefund $refund): void
    {
        $session = $refund->paymentSession;
        /** @var Lead $lead */
        $lead = $this->leads->findForUpdate($session->lead_id);

        $payment = $refund->payment ?? $this->payments->findByExternalId($session->hyperswitch_payment_id);
        if ($payment && $payment->status === PaymentRecordStatusEnum::REUSSI) {
            $payment->update([
                'status' => PaymentRecordStatusEnum::REMBOURSE,
                'status_changed_at' => now(),
                'status_changed_by' => $refund->requested_by,
            ]);
        }

        $session->update(['status' => PaymentSessionStatusEnum::REMBOURSEE]);

        $previousTotal = $lead->expected_revenue !== null ? (string) $lead->expected_revenue : null;
        $newTotal = null;
        if ($previousTotal !== null) {
            $newTotal = bcsub($previousTotal, (string) $refund->amount, 2);
            // Nothing left of the contract: the next payment link sets a new total
            if (bccomp($newTotal, '0', 2) <= 0) {
                $newTotal = null;
            }
            $lead->update(['expected_revenue' => $newTotal]);
        }

        $this->paymentService->recalculatePaymentStatus($lead);

        $author = $refund->requester ?? $session->creator;
        if ($author) {
            $this->leadService->addNote($lead, $author, sprintf(
                'Remboursement de %s : %s — total du contrat %s → %s. Motif : %s',
                $session->reference,
                $this->euros((string) $refund->amount),
                $previousTotal !== null ? $this->euros($previousTotal) : 'non défini',
                $newTotal !== null ? $this->euros($newTotal) : 'non défini',
                $refund->reason,
            ));
        }
    }

    /**
     * 404 on a refund whose creation was unclear: Hyperswitch never got it,
     * so nothing was refunded — the agent may ask again (new refund_id).
     */
    protected function markNotCreated(PaymentRefund $refund): PaymentRefund
    {
        $refund->update([
            'status' => PaymentRefundStatusEnum::ECHOUE,
            'provider_status' => 'not_found',
            'error_message' => 'Remboursement non créé chez Hyperswitch : vous pouvez réessayer.',
            'last_synced_at' => now(),
            'next_sync_at' => null,
        ]);

        $this->afterChange(['refund' => $refund, 'previous' => PaymentRefundStatusEnum::A_VERIFIER, 'changed' => true], notify: true);

        return $refund;
    }

    /**
     * No usable answer from Hyperswitch: keep the status, try again later.
     * A refund is never closed without a verified answer.
     */
    protected function reschedule(PaymentRefund $refund): void
    {
        $refund->refresh();
        if (! $refund->isPending()) {
            return;
        }

        $attempts = $refund->sync_attempts + 1;
        $refund->update([
            'sync_attempts' => $attempts,
            'next_sync_at' => RefundSyncSchedule::nextCheckAt($refund->status, $attempts, $refund->created_at, now()),
        ]);
    }

    /* ── Checks ──────────────────────────────────────────────────────── */

    /**
     * The session can be refunded: paid, its payment received through
     * Hyperswitch, and no refund already done or in progress.
     *
     * @throws ValidationException
     */
    protected function ensureRefundable(PaymentSession $session): Payment
    {
        if ($session->status !== PaymentSessionStatusEnum::PAYEE || ! $session->hyperswitch_payment_id) {
            throw ValidationException::withMessages([
                'refund' => "Seule une demande de paiement payée peut être remboursée (celle-ci est « {$session->status->label()} »).",
            ]);
        }

        $payment = $this->payments->findByExternalId($session->hyperswitch_payment_id);
        if (! $payment || $payment->source !== PaymentSourceEnum::HYPERSWITCH || $payment->status !== PaymentRecordStatusEnum::REUSSI) {
            throw ValidationException::withMessages([
                'refund' => "Aucun paiement reçu via Hyperswitch n'est enregistré pour {$session->reference}.",
            ]);
        }

        if ($blocking = $this->refunds->findBlockingForSession($session->id)) {
            throw ValidationException::withMessages([
                'refund' => $blocking->status === PaymentRefundStatusEnum::REUSSI
                    ? "{$session->reference} a déjà été remboursée."
                    : "Un remboursement de {$session->reference} est déjà en cours ({$blocking->status->label()}) : vérifiez-le plutôt que d'en créer un autre.",
            ]);
        }

        return $payment;
    }

    /**
     * Fresh check with Hyperswitch (doc §8 step 1): the payment must really
     * be succeeded, for the session's full amount.
     *
     * @throws ValidationException
     */
    protected function ensurePaidAtHyperswitch(PaymentSession $session): void
    {
        try {
            $payment = $this->hyperswitch->retrievePayment($session->hyperswitch_payment_id);
        } catch (HyperswitchUncertainException $e) {
            report($e);

            throw ValidationException::withMessages([
                'refund' => "Impossible de vérifier le paiement {$session->reference} auprès d'Hyperswitch pour le moment : réessayez dans quelques instants.",
            ]);
        } catch (ApiException $e) {
            throw ValidationException::withMessages(['refund' => $e->getMessage()]);
        }

        if (($payment['status'] ?? null) !== 'succeeded') {
            throw ValidationException::withMessages([
                'refund' => "Hyperswitch n'indique pas ce paiement comme réussi (statut : ".($payment['status'] ?? 'inconnu').') : il ne peut pas être remboursé.',
            ]);
        }

        $received = (int) ($payment['amount_received'] ?? $payment['amount'] ?? 0);
        if ($received !== HyperswitchClient::toMinorUnits((string) $session->amount)) {
            throw ValidationException::withMessages([
                'refund' => "Le montant reçu chez Hyperswitch ne correspond pas à celui de {$session->reference} : remboursement bloqué, vérifiez le paiement.",
            ]);
        }
    }

    /* ── Side effects (best effort, after the commit) ────────────────── */

    /**
     * @param  array{refund: PaymentRefund, previous: PaymentRefundStatusEnum, changed: bool}  $outcome
     */
    protected function afterChange(array $outcome, bool $notify): void
    {
        if (! $outcome['changed']) {
            return;
        }

        $refund = $outcome['refund'];
        $refund->loadMissing(['paymentSession.lead', 'requester']);

        $this->broadcast($refund->paymentSession);

        if ($notify && $refund->status->isFinal() && $refund->requester) {
            try {
                $refund->requester->notify(new PaymentRefundNotification($refund));
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * The lead page listening on "leads.{id}" reloads its payments.
     */
    protected function broadcast(PaymentSession $session): void
    {
        try {
            broadcast(new PaymentSessionUpdated($session->refresh()));
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function euros(string $amount): string
    {
        return number_format((float) $amount, 2, ',', ' ').' €';
    }
}
