<?php

namespace App\Services;

use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentRecordStatusEnum;
use App\Enums\PaymentSessionStatusEnum;
use App\Enums\PaymentSourceEnum;
use App\Events\PaymentSessionUpdated;
use App\Exceptions\ApiException;
use App\Exceptions\HyperswitchUncertainException;
use App\Models\PaymentSession;
use App\Models\User;
use App\Notifications\PaymentResultNotification;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Repositories\Contracts\PaymentSessionRepositoryInterface;
use App\Support\PaymentSyncSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Status of the payment links, pulled from Hyperswitch (no webhook).
 *
 * Three triggers call syncFromProvider():
 *  1. the client's return page (onClientReturn, then runDueForcedSync);
 *  2. a user who needs a fresh status (refresh button, new link: refresh);
 *  3. the scheduled job (syncDueSessions, every 15 s).
 * Client back: a normal read, then one forced sync (Sogecommerce) 30 s
 * later. Client not back: normal reads every 15 s during the first 10
 * minutes. Then one forced check just after the link's expiry (15 min),
 * which closes it: paid, refused, or EXPIREE (PaymentSyncSchedule).
 *
 * Each answer updates the session, records the attempt as a payment grouped
 * under it, recalculates the lead's payment status, and — when something
 * changed — broadcasts it, notifies the agents and reports failures to Plane.
 */
class PaymentSyncService
{
    public function __construct(
        protected PaymentSessionRepositoryInterface $sessions,
        protected PaymentRepositoryInterface $payments,
        protected PaymentService $paymentService,
        protected HyperswitchClient $hyperswitch,
        protected PaymentLinkSender $linkSender,
    ) {}

    /* ── Triggers ────────────────────────────────────────────────────── */

    /**
     * The client came back: one normal read. If the status is not final,
     * one forced sync is planned 30 s after this return (nextSchedule()).
     * Only the first return does this: a reload of the page doesn't
     * trigger more syncs.
     */
    public function onClientReturn(PaymentSession $session): PaymentSession
    {
        if ($session->returned_at || ! $session->isPending()) {
            if (! $session->returned_at) {
                $session->update(['returned_at' => now()]);
            }

            return $session;
        }

        // Set before the read, so the schedule plans the forced syncs
        $session->update(['returned_at' => now(), 'forced_syncs' => 0]);

        try {
            return $this->syncFromProvider($session);
        } catch (Throwable $e) {
            report($e); // rescheduled: the forced syncs are still planned

            return $session->refresh();
        }
    }

    /**
     * Result page polling: the saved state; runs the planned forced sync
     * (30 s after the return) once it is due (only one poll or job run
     * claims it).
     */
    public function runDueForcedSync(PaymentSession $session): PaymentSession
    {
        if (! $session->isPending() || ! $session->force_pending || ! $session->next_sync_at || $session->next_sync_at->isFuture()) {
            return $session;
        }

        if (! $this->claimForcedSync($session)) {
            return $session->refresh();
        }

        try {
            return $this->syncFromProvider($session, force: true);
        } catch (Throwable $e) {
            report($e);

            return $session->refresh();
        }
    }

    /**
     * A user needs the status now (refresh button, new link): a normal
     * read, then a forced sync only if the client came back or the payment
     * is processing, and at most once a minute.
     *
     * @throws ApiException|HyperswitchUncertainException
     */
    public function refresh(PaymentSession $session): PaymentSession
    {
        $session = $this->syncFromProvider($session);

        $lastForced = $session->last_forced_sync_at;
        if ($session->status === PaymentSessionStatusEnum::OUVERTE
            && $this->shouldForce($session)
            && (! $lastForced || $lastForced->diffInSeconds(now(), true) >= PaymentSyncSchedule::USER_FORCED_MIN_INTERVAL)) {
            $session = $this->syncFromProvider($session, force: true);
        }

        return $session;
    }

    /**
     * Scheduled job: follow the pending sessions that are due.
     *
     * @return int number of sessions synced
     */
    public function syncDueSessions(int $limit = 50): int
    {
        $count = 0;

        foreach ($this->sessions->dueForSync($limit, PaymentSyncSchedule::DUE_TOLERANCE) as $session) {
            try {
                // The planned forced sync (30 s after the return), or a
                // normal read (client not back, first 10 minutes)
                if (! $session->force_pending) {
                    $this->syncFromProvider($session);
                } elseif ($this->claimForcedSync($session)) {
                    $this->syncFromProvider($session, force: true);
                }
                $count++;
            } catch (Throwable $e) {
                // Unclear answer / Hyperswitch down: already rescheduled, go on
                report($e);
            }
        }

        return $count;
    }

    /* ── The sync itself ─────────────────────────────────────────────── */

    /**
     * Ask Hyperswitch for the payment and apply the answer (idempotent).
     * Final statuses never move back.
     *
     * @throws ApiException|HyperswitchUncertainException when Hyperswitch
     *                                                    gives no usable answer (the session is rescheduled first)
     */
    public function syncFromProvider(PaymentSession $session, bool $force = false): PaymentSession
    {
        if (! $session->isPending() || ! $session->hyperswitch_payment_id) {
            return $session;
        }

        try {
            $payment = $this->hyperswitch->retrievePayment($session->hyperswitch_payment_id, $force);
        } catch (ApiException $e) {
            if ($e instanceof HyperswitchUncertainException || $e->getStatusCode() !== 404) {
                $this->reschedule($session, $force);

                throw $e;
            }
            $payment = null; // 404: the payment was never created
        }

        $outcome = DB::transaction(fn () => $this->apply($session->id, $payment, $force));

        if ($outcome['changed']) {
            $this->afterChange($outcome['session'], $outcome['previous'], $payment);
        }

        return $outcome['session'];
    }

    /**
     * Apply one Hyperswitch answer to the locked session.
     *
     * @param  array<string, mixed>|null  $payment  Hyperswitch payment (null = not found)
     * @return array{session: PaymentSession, previous: PaymentSessionStatusEnum, changed: bool}
     */
    protected function apply(int $sessionId, ?array $payment, bool $forced): array
    {
        /** @var PaymentSession $session */
        $session = $this->sessions->findForUpdate($sessionId);
        $previous = $session->status;

        // Another sync already closed it
        if (! $session->isPending()) {
            return ['session' => $session, 'previous' => $previous, 'changed' => false];
        }

        $now = now();
        $attributes = [
            'provider_status' => $payment['status'] ?? 'not_found',
            'provider_payload' => $payment ?? ['verification' => 'not_found'],
            'last_synced_at' => $now,
        ];

        if ($payment) {
            // Connector ids and the bank code/message, for support
            $attributes += array_filter([
                'merchant_connector_id' => $payment['merchant_connector_id'] ?? null,
                'connector_transaction_id' => $payment['connector_transaction_id'] ?? null,
            ]);
            $attributes += HyperswitchClient::errorOf($payment);

            // Expiry of the link (older sessions didn't save it at creation)
            if (! $session->expires_at && ($expiresAt = HyperswitchClient::expiresAtOf($payment))) {
                $attributes['expires_at'] = $expiresAt;
            }
        }

        if ($forced) {
            $attributes['last_forced_sync_at'] = $now;
            $attributes['forced_syncs'] = $session->forced_syncs + 1;
        }

        // The status and the schedule below read these new values
        $session->fill($attributes);

        $newStatus = $this->statusFor($session, $payment, $forced);

        if ($newStatus === PaymentSessionStatusEnum::OUVERTE && $previous === PaymentSessionStatusEnum::A_VERIFIER) {
            // The uncertain creation did happen: keep its link
            $attributes['payment_url'] = HyperswitchClient::extractPaymentUrl($payment);
        }

        if ($newStatus !== null && $newStatus !== $previous) {
            $attributes['status'] = $newStatus;
            if ($newStatus === PaymentSessionStatusEnum::PAYEE) {
                $attributes['paid_at'] = $now;
            }
        }

        $attributes += ($newStatus ?? $previous)->isFinal()
            ? ['next_sync_at' => null, 'force_pending' => false]
            : $this->nextSchedule($session);

        $session->update($attributes);

        // An expired link was never paid: no payment to record
        $paymentChanged = $payment
            && $session->status !== PaymentSessionStatusEnum::EXPIREE
            && $this->recordAttempt($session, $payment);

        return [
            'session' => $session,
            'previous' => $previous,
            'changed' => $paymentChanged || $session->status !== $previous,
        ];
    }

    /**
     * Session status after this answer (null = unchanged).
     *
     * Past the link's expiry, Hyperswitch still says "waiting for the
     * client"; a forced sync then gets "failed / PSP_010 transaction not
     * found" when the client never validated a card. Both mean the link
     * expired unpaid: EXPIREE, not a failed payment. A real refusal (bank
     * code, a transaction at Sogecommerce) stays ECHOUEE.
     *
     * @param  array<string, mixed>|null  $payment
     */
    protected function statusFor(PaymentSession $session, ?array $payment, bool $forced): ?PaymentSessionStatusEnum
    {
        if ($payment === null) {
            // Never created at Hyperswitch: nothing to pay
            return PaymentSessionStatusEnum::ANNULEE;
        }

        $pastExpiry = $session->status === PaymentSessionStatusEnum::OUVERTE
            && PaymentSyncSchedule::isPastExpiry($session->expires_at, now());

        if ($pastExpiry && HyperswitchClient::neverAttempted($payment)) {
            return PaymentSessionStatusEnum::EXPIREE;
        }

        if ($mapped = PaymentSessionStatusEnum::fromProvider($payment['status'] ?? null)) {
            return $mapped;
        }

        if ($session->status === PaymentSessionStatusEnum::A_VERIFIER) {
            // Exists but not final: usable if Hyperswitch gave its link
            return HyperswitchClient::extractPaymentUrl($payment)
                ? PaymentSessionStatusEnum::OUVERTE
                : PaymentSessionStatusEnum::ANNULEE;
        }

        // Still "waiting for the client" after the bank was asked: expired
        // unpaid (a payment being processed is never expired)
        if ($pastExpiry && $forced && ! PaymentSessionStatusEnum::providerIsProcessing($payment['status'] ?? null)) {
            return PaymentSessionStatusEnum::EXPIREE;
        }

        return null;
    }

    /**
     * Record (or update) the payment of this Hyperswitch payment, grouped
     * under its session, then recalculate the lead's payment status.
     *
     * @param  array<string, mixed>  $payment
     * @return bool whether a payment was created or changed
     */
    protected function recordAttempt(PaymentSession $session, array $payment): bool
    {
        $status = PaymentRecordStatusEnum::fromProvider($payment['status'] ?? null);
        if (! $status) {
            return false; // the client hasn't paid (yet)
        }

        // Bank refusal: the precise Sogecommerce code/message, for support
        $error = HyperswitchClient::errorOf($payment);
        $failure = $status === PaymentRecordStatusEnum::ECHOUE
            ? ['failure_reason' => $error['error_message'] ?? 'Paiement refusé', 'failure_code' => $error['error_code']]
            : ['failure_reason' => null, 'failure_code' => null];

        $existing = $this->payments->findByExternalId($session->hyperswitch_payment_id);

        if ($existing) {
            // Same status, or already final (never moves back)
            if ($existing->status === $status || in_array($existing->status, [PaymentRecordStatusEnum::REUSSI, PaymentRecordStatusEnum::ECHOUE], true)) {
                return false;
            }

            $existing->update([
                'status' => $status,
                ...$failure,
                'provider_payload' => $payment,
                'status_changed_at' => now(),
            ]);
        } else {
            if ($status === PaymentRecordStatusEnum::ANNULE) {
                return false; // cancelled before any attempt: nothing to record
            }

            $lead = $session->lead;
            $createdBy = $session->created_by ?? $lead->created_by ?? $lead->assigned_to;
            if (! $createdBy) {
                Log::warning('payment sync: no user to attach the payment to', ['session' => $session->reference]);

                return false;
            }

            $this->payments->create([
                'lead_id' => $session->lead_id,
                'payment_session_id' => $session->id,
                'amount' => $session->amount,
                'status' => $status,
                'source' => PaymentSourceEnum::HYPERSWITCH,
                'external_id' => $session->hyperswitch_payment_id,
                'payment_date' => now()->toDateString(),
                'payment_method' => PaymentMethodEnum::PAYMENT_LINK,
                'reference_number' => $session->reference,
                ...$failure,
                'provider_payload' => $payment,
                'status_changed_at' => now(),
                'created_by' => $createdBy,
            ]);
        }

        $this->paymentService->recalculatePaymentStatus($session->lead);

        return true;
    }

    /**
     * Side effects of a change, after the commit. Each one is best effort:
     * none of them may break the sync.
     *
     * @param  array<string, mixed>|null  $payment
     */
    protected function afterChange(PaymentSession $session, PaymentSessionStatusEnum $previous, ?array $payment): void
    {
        $session->loadMissing(['lead', 'creator']);
        $statusChanged = $session->status !== $previous;

        // An uncertain creation that did happen: the client gets the link now
        if ($previous === PaymentSessionStatusEnum::A_VERIFIER && $session->status === PaymentSessionStatusEnum::OUVERTE) {
            $this->linkSender->send($session);
        }

        try {
            broadcast(new PaymentSessionUpdated($session));
        } catch (Throwable $e) {
            report($e);
        }

        if ($statusChanged && in_array($session->status, [PaymentSessionStatusEnum::PAYEE, PaymentSessionStatusEnum::ECHOUEE, PaymentSessionStatusEnum::EXPIREE], true)) {
            try {
                collect([$session->creator, $session->lead->assignedAgent])
                    ->filter()
                    ->unique('id')
                    ->each(fn (User $user) => $user->notify(new PaymentResultNotification($session)));
            } catch (Throwable $e) {
                report($e);
            }
        }

        if ($statusChanged && $session->status === PaymentSessionStatusEnum::ECHOUEE && $payment) {
            $this->reportFailureToPlane($payment);
        }
    }

    /**
     * Failed payments become a task in Plane (PlaneService, from the
     * feat/plane-feedback branch). Skipped while that class is absent.
     *
     * @param  array<string, mixed>  $payment
     */
    protected function reportFailureToPlane(array $payment): void
    {
        $plane = 'App\\Services\\PlaneService';
        if (! class_exists($plane)) {
            return;
        }

        try {
            app($plane)->reportFailedHyperswitchPayment($payment);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /* ── Scheduling ──────────────────────────────────────────────────── */

    /**
     * A forced sync asked by a user only makes sense once the client came
     * back from the bank, while the payment is being processed, or once
     * the link has expired (only the bank can then close it).
     */
    protected function shouldForce(PaymentSession $session): bool
    {
        return $session->returned_at !== null
            || PaymentSessionStatusEnum::providerIsProcessing($session->provider_status)
            || PaymentSyncSchedule::isPastExpiry($session->expires_at, now());
    }

    /**
     * Take the planned forced sync (only one poll or job run gets it).
     */
    protected function claimForcedSync(PaymentSession $session): bool
    {
        return DB::transaction(function () use ($session) {
            /** @var PaymentSession $locked */
            $locked = $this->sessions->findForUpdate($session->id);
            if (! $locked->force_pending || ! $locked->isPending()) {
                return false;
            }
            $locked->update(['force_pending' => false]);

            return true;
        });
    }

    /**
     * Next check of a payment still not final:
     *  - client came back → the forced sync 30 s after the return;
     *  - client not back → a normal read every 15 s during the first 10
     *    minutes of the link;
     *  - then only the link's expiry: one forced check just after it
     *    (PaymentSyncSchedule::nextExpiryCheck), which closes it.
     *
     * @return array<string, mixed>
     */
    protected function nextSchedule(PaymentSession $session): array
    {
        $now = now();

        if ($session->returned_at && ($forcedAt = PaymentSyncSchedule::nextForcedAt($session->returned_at, $session->forced_syncs))) {
            return ['force_pending' => true, 'next_sync_at' => $forcedAt->greaterThan($now) ? $forcedAt : $now];
        }

        if (! $session->returned_at && ($delay = PaymentSyncSchedule::nextNormalDelay($session->created_at, $now)) !== null) {
            return [
                'force_pending' => false,
                'next_sync_at' => $now->copy()->addSeconds($delay),
                'sync_attempts' => $session->sync_attempts + 1,
            ];
        }

        $checkAt = PaymentSyncSchedule::nextExpiryCheck($session->expires_at, $session->provider_status, $now);

        return ['force_pending' => $checkAt !== null, 'next_sync_at' => $checkAt];
    }

    /**
     * No usable answer from Hyperswitch: keep the status, try again later.
     * A forced sync that got no answer still counts. A link is never
     * closed without a verified answer.
     */
    protected function reschedule(PaymentSession $session, bool $forced): void
    {
        $session->refresh();
        if (! $session->isPending()) {
            return;
        }

        if ($forced) {
            $session->forced_syncs++;
        }

        $session->update(['forced_syncs' => $session->forced_syncs, ...$this->nextSchedule($session)]);
    }
}
