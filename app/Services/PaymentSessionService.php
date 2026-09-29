<?php

namespace App\Services;

use App\Enums\PaymentSessionStatusEnum;
use App\Exceptions\ApiException;
use App\Exceptions\HyperswitchUncertainException;
use App\Models\Lead;
use App\Models\PaymentSession;
use App\Models\User;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Repositories\Contracts\PaymentSessionRepositoryInterface;
use App\Support\PaymentResultPage;
use App\Support\PaymentSyncSchedule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Payment sessions = payment requests sent to the client through
 * Hyperswitch: create the payment, get its link, e-mail it; refresh or
 * cancel it; find it from the client's result page.
 *
 * A lead has at most one pending session (OUVERTE or A_VERIFIER), so the
 * client is never asked to pay twice. The Hyperswitch payment_id is chosen
 * by the CRM before the call; an unclear answer makes the session
 * A_VERIFIER, never a retry. The amount can't exceed what is left to
 * collect on the contract total (the first link sets that total; it is
 * changed with PaymentService::updateContractTotal()).
 *
 * The status of the sessions is followed by PaymentSyncService.
 */
class PaymentSessionService
{
    public function __construct(
        protected PaymentSessionRepositoryInterface $sessions,
        protected LeadRepositoryInterface $leads,
        protected PaymentService $paymentService,
        protected PaymentSyncService $sync,
        protected PaymentLinkSender $linkSender,
        protected HyperswitchClient $hyperswitch,
        protected LeadService $leadService,
    ) {}

    public function listForLead(Lead $lead): Collection
    {
        return $this->sessions->listForLead($lead->id);
    }

    /**
     * Payment links of every lead the user can see (Payments page).
     *
     * @param  array{search?: string, status?: string, lead_id?: int|string}  $filters
     */
    public function paginateForUser(User $user, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->sessions->paginateScoped($filters, $perPage, $this->leadService->visibilityScope($user));
    }

    /**
     * Create the payment request. Returns the session OUVERTE (link sent),
     * or A_VERIFIER when Hyperswitch gave no clear answer: then the payment
     * may exist and is checked with its id, never created a second time.
     *
     * @throws ValidationException|ApiException
     */
    public function create(Lead $lead, User $creator, string|float|int $amount, ?string $email = null): PaymentSession
    {
        $this->refreshPreviousRequest($lead);

        $token = PaymentResultPage::generateToken();

        $session = DB::transaction(fn () => $this->reserve($lead, $creator, $amount, $email, PaymentResultPage::hash($token)));

        try {
            $result = $this->hyperswitch->createRedirectPayment(
                paymentId: $session->hyperswitch_payment_id,
                amount: (string) $session->amount,
                currency: $session->currency,
                email: $session->client_email,
                reference: $session->reference,
                customerId: 'lead-'.$lead->id,
                metadata: ['lead_id' => (string) $lead->id, 'source' => 'solva-crm'],
                returnUrl: $this->hyperswitch->resultPageUrl($token),
            );
        } catch (HyperswitchUncertainException $e) {
            $session->update(['status' => PaymentSessionStatusEnum::A_VERIFIER]);
            report($e);

            return $session->load('creator');
        } catch (Throwable $e) {
            $this->sessions->delete($session->id);

            throw $e;
        }

        $session->update([
            'hyperswitch_payment_id' => $result['payment_id'],
            'merchant_connector_id' => $result['merchant_connector_id'],
            'payment_url' => $result['payment_url'],
            'provider_status' => $result['status'],
            'error_code' => $result['error_code'],
            'error_message' => $result['error_message'],
            'provider_payload' => $result['payload'],
        ]);

        $this->ensureCreatedAsExpected($session, $result);

        $this->linkSender->send($session);

        return $session->load('creator');
    }

    /**
     * User "Refresh" / "Check": asks Hyperswitch for the current status.
     *
     * @throws ValidationException|ApiException
     */
    public function verify(PaymentSession $session): PaymentSession
    {
        if (! $session->isPending()) {
            throw ValidationException::withMessages([
                'session' => "Cette demande de paiement est « {$session->status->label()} » : il n'y a rien à vérifier.",
            ]);
        }

        return $this->sync->refresh($session)->load('creator');
    }

    /**
     * Close a pending session in the CRM so a new link can be sent.
     */
    public function cancel(PaymentSession $session): PaymentSession
    {
        if (! $session->isPending()) {
            throw ValidationException::withMessages([
                'session' => "Cette demande de paiement est « {$session->status->label()} » : elle ne peut plus être annulée.",
            ]);
        }

        $session->update([
            'status' => PaymentSessionStatusEnum::ANNULEE,
            'next_sync_at' => null,
            'force_pending' => false,
        ]);

        return $session->load('creator');
    }

    /* ── Result page (public, the client came back from the bank) ─────── */

    /**
     * The session of a result-page token, or null if unknown.
     */
    public function findByToken(string $token): ?PaymentSession
    {
        return $this->sessions->findByTokenHash(PaymentResultPage::hash($token));
    }

    public function resultPageExpired(PaymentSession $session): bool
    {
        return PaymentResultPage::isExpired($session->returned_at, now(), (int) config('services.hyperswitch.result_page_ttl', 120));
    }

    /* ── Creation steps ──────────────────────────────────────────────── */

    /**
     * Before a new link: fresh status of the lead's pending request, if
     * any. Without a clear answer we don't know whether it was paid, so
     * no new link.
     */
    protected function refreshPreviousRequest(Lead $lead): void
    {
        $open = $this->sessions->findOpenForLead($lead->id);
        if (! $open) {
            return;
        }

        try {
            $this->sync->refresh($open);
        } catch (Throwable $e) {
            report($e);
            $this->reportCheckFailureToPlane($open, $e);

            throw ValidationException::withMessages([
                'session' => "Impossible de vérifier le paiement {$open->reference} auprès d'Hyperswitch pour le moment : réessayez dans quelques instants avant d'envoyer un nouveau lien.",
            ]);
        }
    }

    /**
     * The previous request couldn't be checked with Hyperswitch, so the
     * agent is blocked: report it to Plane, once per payment per hour.
     * Best effort: Plane never blocks the agent.
     */
    protected function reportCheckFailureToPlane(PaymentSession $session, Throwable $error): void
    {
        if (! Cache::add("plane:payment-check-failed:{$session->id}", true, now()->addHour())) {
            return;
        }

        try {
            app(PlaneService::class)->reportHyperswitchCheckFailure($session, $error);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Checks, under the lead's lock, then the session saved with its
     * Hyperswitch payment_id (before calling Hyperswitch).
     */
    protected function reserve(Lead $lead, User $creator, string|float|int $amount, ?string $email, string $tokenHash): PaymentSession
    {
        /** @var Lead $lead */
        $lead = $this->leads->findForUpdate($lead->id);

        $this->paymentService->ensureLeadIsOpen($lead);

        $amount = number_format((float) $amount, 2, '.', '');
        if (bccomp($amount, '0', 2) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Le montant doit être supérieur à 0.']);
        }

        $email = $email ?: $lead->email;
        if (! $email) {
            throw ValidationException::withMessages([
                'email' => 'Le lead n\'a pas d\'adresse e-mail : indiquez celle du client.',
            ]);
        }

        // Still pending after refreshPreviousRequest(): no second payment
        if ($open = $this->sessions->findOpenForLead($lead->id)) {
            $state = $open->provider_status ? " (Hyperswitch : {$open->provider_status})" : '';

            throw ValidationException::withMessages([
                'session' => $open->status === PaymentSessionStatusEnum::A_VERIFIER
                    ? "La demande {$open->reference} n'a pas encore pu être vérifiée auprès d'Hyperswitch : réessayez dans quelques instants avant d'envoyer un nouveau lien."
                    : "Le paiement {$open->reference} est toujours en attente{$state} : le client n'a pas encore payé. Annulez ce lien avant d'en envoyer un nouveau.",
            ]);
        }

        if ($lead->expected_revenue === null) {
            // First request: its amount becomes the contract total
            $lead->update(['expected_revenue' => $amount]);
        } else {
            $remaining = $this->paymentService->remainingToCollect($lead);

            if (bccomp($remaining, '0', 2) <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Il ne reste rien à encaisser sur ce lead. Si le contrat a changé, modifiez d\'abord son montant total.',
                ]);
            }

            if (bccomp($amount, $remaining, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => "Le montant dépasse le solde restant ({$remaining} €). Si le contrat a changé, modifiez d'abord son montant total.",
                ]);
            }
        }

        return $this->sessions->create([
            'lead_id' => $lead->id,
            'reference' => 'PAY-'.$lead->id.'-'.($this->sessions->countForLead($lead->id) + 1),
            'amount' => $amount,
            'currency' => strtoupper((string) config('services.hyperswitch.currency', 'EUR')),
            'status' => PaymentSessionStatusEnum::OUVERTE,
            'hyperswitch_payment_id' => HyperswitchClient::generatePaymentId(),
            'public_token_hash' => $tokenHash,
            'client_email' => $email,
            // Normal follow-up starts right away: the client may pay, or be
            // refused, without ever coming back to the CRM
            'next_sync_at' => now()->addSeconds(PaymentSyncSchedule::firstNormalDelay()),
            'created_by' => $creator->id,
        ]);
    }

    /**
     * Hyperswitch created the payment, but it can't be sent to the client:
     *  - refused by the connector at creation (HTTP success, status failed);
     *  - routed to another connector than the forced one.
     *
     * @param  array<string, mixed>  $result  HyperswitchClient::createRedirectPayment()
     *
     * @throws ApiException
     */
    protected function ensureCreatedAsExpected(PaymentSession $session, array $result): void
    {
        if ($result['status'] === 'failed') {
            $session->update(['status' => PaymentSessionStatusEnum::ECHOUEE, 'next_sync_at' => null]);

            throw new ApiException(
                'Sogecommerce a refusé la création du paiement : '.($result['error_message'] ?? 'motif inconnu')
                    .($result['error_code'] ? " (code {$result['error_code']})" : ''),
                422,
            );
        }

        $expected = $this->hyperswitch->connector();
        if ($result['connector'] && $result['connector'] !== $expected) {
            Log::error('hyperswitch: unexpected connector for a payment', [
                'crm_reference' => $session->reference,
                'payment_id' => $session->hyperswitch_payment_id,
                'expected' => $expected,
                'connector' => $result['connector'],
            ]);
            $session->update([
                'status' => PaymentSessionStatusEnum::ANNULEE,
                'next_sync_at' => null,
                'error_message' => "Connecteur inattendu : {$result['connector']}",
            ]);

            throw new ApiException("Le paiement n'a pas été créé sur le bon connecteur ({$result['connector']}) : le lien n'a pas été envoyé.", 502);
        }
    }
}
