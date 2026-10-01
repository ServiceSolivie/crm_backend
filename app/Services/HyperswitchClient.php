<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Exceptions\HyperswitchUncertainException;
use App\Support\PaymentResultPage;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Thin HTTP client for the Hyperswitch API (v1), the only payment API the
 * CRM calls (never Sogecommerce directly, never any card data).
 *
 * Payments: create a card-redirect payment (3DS, automatic capture, forced
 * to one connector) and read its status (normal or force_sync).
 *
 * Answers are classified the same way everywhere:
 *  - network error / timeout / 5xx: HyperswitchUncertainException — the
 *    result is unknown, never replay a write blindly, check it first;
 *  - 4xx: ApiException — request refused, nothing was done;
 *  - 2xx: the operation exists (it may still have status "failed").
 *
 * Logs only carry technical identifiers, statuses and bank codes: never the
 * API key, nor the raw body (it contains personal data).
 */
class HyperswitchClient
{
    /** Sogecommerce error code: no transaction for this payment */
    public const TRANSACTION_NOT_FOUND = 'PSP_010';

    public function __construct(
        protected string $baseUrl,
        protected ?string $apiKey,
        protected ?string $profileId,
        protected string $connector,
        protected string $returnUrl,
        protected int $timeout = 20,
        protected int $connectTimeout = 5,
    ) {}

    public static function fromConfig(): self
    {
        $config = config('services.hyperswitch');

        return new self(
            baseUrl: self::normalizeBaseUrl((string) $config['base_url']),
            apiKey: $config['api_key'] ?: null,
            profileId: $config['profile_id'] ?: null,
            connector: (string) $config['connector'],
            returnUrl: $config['return_url'] ?: (string) config('app.frontend_url'),
            timeout: (int) $config['timeout'],
            connectTimeout: (int) ($config['connect_timeout'] ?? 5),
        );
    }

    public static function normalizeBaseUrl(string $url): string
    {
        return preg_replace('#/payments/?$#', '', rtrim($url, '/')) ?: rtrim($url, '/');
    }

    /**
     * Page the client comes back to after paying (shows the result).
     */
    public function resultPageUrl(string $token): string
    {
        return PaymentResultPage::url($this->returnUrl, $token);
    }

    public function connector(): string
    {
        return $this->connector;
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== null && $this->profileId !== null;
    }

    /* ── Payments ────────────────────────────────────────────────────── */

    /**
     * Create the payment at Hyperswitch with the id chosen by the CRM
     * ($paymentId, see generatePaymentId()), so the CRM knows it even if
     * the answer is lost.
     *
     * Returns the payment: status "requires_customer_action" with its link,
     * or "failed" (created but refused by the connector, no link).
     *
     * @param  array<string, mixed>  $metadata
     * @return array{payment_id: string, status: ?string, payment_url: ?string, connector: ?string, merchant_connector_id: ?string, expires_at: ?CarbonInterface, error_code: ?string, error_message: ?string, payload: array<string, mixed>}
     *
     * @throws ApiException|HyperswitchUncertainException
     */
    public function createRedirectPayment(string $paymentId, string $amount, string $currency, string $email, string $reference, ?string $customerId = null, array $metadata = [], ?string $returnUrl = null): array
    {
        $this->ensureConfigured();

        $payload = $this->buildPaymentPayload($paymentId, $amount, $currency, $email, $reference, $customerId, $metadata, $returnUrl);
        $context = ['crm_reference' => $reference, 'payment_id' => $paymentId];

        $body = $this->send('POST /payments', $context, fn (PendingRequest $http) => $http->post('/payments', $payload), 'Hyperswitch a refusé la demande');

        $url = self::extractPaymentUrl($body);
        $status = $body['status'] ?? null;

        if (! $url && $status !== 'failed') {
            throw new HyperswitchUncertainException('Hyperswitch n\'a pas renvoyé de lien de paiement : la demande est à vérifier.');
        }

        return [
            'payment_id' => $body['payment_id'] ?? $paymentId,
            'status' => $status,
            'payment_url' => $url,
            'connector' => $body['connector'] ?? null,
            'merchant_connector_id' => $body['merchant_connector_id'] ?? null,
            'expires_at' => self::expiresAtOf($body),
            ...self::errorOf($body),
            'payload' => self::withoutSecrets($body),
        ];
    }

    /**
     * Current state of a payment (GET /payments/{id}); force_sync asks the
     * connector (Sogecommerce Order/Get) — never in a tight loop.
     *
     * - 404: ApiException with code 404 (the payment does not exist)
     *
     * @return array<string, mixed>
     *
     * @throws ApiException|HyperswitchUncertainException
     */
    public function retrievePayment(string $paymentId, bool $forceSync = false): array
    {
        $this->ensureConfigured();

        return self::withoutSecrets($this->send(
            'GET /payments/{id}'.($forceSync ? ' (force_sync)' : ''),
            ['payment_id' => $paymentId],
            fn (PendingRequest $http) => $http->get('/payments/'.rawurlencode($paymentId), $forceSync ? ['force_sync' => 'true'] : []),
            "Hyperswitch n'a pas pu retrouver le paiement {$paymentId}",
        ));
    }

    /* ── Payload builders (pure) ─────────────────────────────────────── */

    /**
     * Body of POST /payments: card redirect with 3D Secure, captured
     * automatically, routed to the configured connector and profile.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public function buildPaymentPayload(string $paymentId, string $amount, string $currency, string $email, string $reference, ?string $customerId = null, array $metadata = [], ?string $returnUrl = null): array
    {
        return [
            'payment_id' => $paymentId,
            'amount' => self::toMinorUnits($amount),
            'currency' => $currency,
            'confirm' => true,
            'capture_method' => 'automatic',
            'authentication_type' => 'three_ds',
            'connector' => [$this->connector],
            'profile_id' => $this->profileId,
            'payment_method' => 'card_redirect',
            'payment_method_type' => 'card_redirect',
            'payment_method_data' => ['card_redirect' => ['card_redirect' => (object) []]],
            'email' => $email,
            'customer' => array_filter(['id' => $customerId, 'email' => $email]),
            'return_url' => $returnUrl ?? $this->returnUrl,
            'merchant_order_reference_id' => $reference,
            'metadata' => ['crm_reference' => $reference, ...$metadata],
        ];
    }

    /**
     * Payment id chosen by the CRM: Hyperswitch requires exactly 30
     * characters ("pay_" + 26 letters/digits).
     */
    public static function generatePaymentId(): string
    {
        return 'pay_'.Str::random(26);
    }

    /**
     * "990.00" (euros) → 99000 (cents): Hyperswitch amounts are in minor units.
     */
    public static function toMinorUnits(string|int|float $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * URL the client must open: the redirect of a confirmed payment, or a
     * payment link when Hyperswitch returns one instead.
     *
     * @param  array<string, mixed>  $response
     */
    public static function extractPaymentUrl(array $response): ?string
    {
        return $response['next_action']['redirect_to_url']
            ?? $response['payment_link']['link']
            ?? null;
    }

    /**
     * Bank refusal: the precise Sogecommerce code/message first (e.g. 51,
     * 39), Hyperswitch's generic classification otherwise.
     *
     * @param  array<string, mixed>  $response
     * @return array{error_code: ?string, error_message: ?string}
     */
    public static function errorOf(array $response): array
    {
        $code = $response['error_code'] ?? $response['unified_code'] ?? null;
        $message = $response['error_message'] ?? $response['unified_message'] ?? null;

        return [
            'error_code' => $code !== null ? mb_substr((string) $code, 0, 50) : null,
            'error_message' => $message !== null ? mb_substr((string) $message, 0, 255) : null,
        ];
    }

    /**
     * The response carries a client_secret that can confirm the payment,
     * also inside sdk_authorization (Base64 with the client_secret and the
     * publishable key): never store them.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public static function withoutSecrets(array $response): array
    {
        unset($response['client_secret'], $response['sdk_authorization']);

        return $response;
    }

    /**
     * When the payment link expires ("expires_on", 15 min after creation by
     * default), or null if absent / unreadable.
     *
     * @param  array<string, mixed>  $response
     */
    public static function expiresAtOf(array $response): ?CarbonInterface
    {
        $value = $response['expires_on'] ?? null;

        return is_string($value) && strtotime($value) !== false ? Carbon::parse($value) : null;
    }

    /**
     * Sogecommerce has no transaction for this payment: the client never
     * validated a card (answer of a force_sync on an unused link).
     *
     * @param  array<string, mixed>  $response
     */
    public static function neverAttempted(array $response): bool
    {
        return ($response['status'] ?? null) === 'failed'
            && ($response['error_code'] ?? null) === self::TRANSACTION_NOT_FOUND
            && empty($response['connector_transaction_id']);
    }

    /**
     * What may be written in the logs: identifiers, statuses and codes —
     * no e-mail, name, address, amount details or secret.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public static function logSummary(array $body): array
    {
        return array_filter([
            'payment_id' => $body['payment_id'] ?? null,
            'status' => $body['status'] ?? null,
            'connector' => $body['connector'] ?? null,
            'merchant_connector_id' => $body['merchant_connector_id'] ?? null,
            'connector_transaction_id' => $body['connector_transaction_id'] ?? null,
            'error_code' => $body['error_code'] ?? ($body['error']['code'] ?? null),
            'error_message' => $body['error_message'] ?? ($body['error']['message'] ?? null),
            'unified_code' => $body['unified_code'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /* ── HTTP ────────────────────────────────────────────────────────── */

    protected function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new ApiException('Le paiement en ligne n\'est pas configuré (clé API ou profil Hyperswitch manquant).', 503);
        }
    }

    /**
     * Send one request and classify the answer (see class docblock).
     *
     * @param  array<string, mixed>  $context  identifiers for the log
     * @param  callable(PendingRequest): Response  $call
     * @return array<string, mixed>
     *
     * @throws ApiException|HyperswitchUncertainException
     */
    protected function send(string $action, array $context, callable $call, string $refusedMessage): array
    {
        $startedAt = microtime(true);
        $http = Http::baseUrl($this->baseUrl)
            ->withHeaders(['api-key' => $this->apiKey])
            ->acceptJson()
            ->asJson()
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout);

        try {
            $response = $call($http);
        } catch (ConnectionException $e) {
            Log::warning("hyperswitch: no answer to {$action}", [
                ...$context,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'error' => $e->getMessage(),
            ]);

            throw new HyperswitchUncertainException('Hyperswitch n\'a pas répondu à temps : l\'opération est à vérifier.');
        }

        $body = $response->json() ?? [];

        Log::info("hyperswitch: {$action}", [
            ...$context,
            'http_status' => $response->status(),
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            ...self::logSummary($body),
        ]);

        if ($response->serverError()) {
            // Hyperswitch failed on its side: the operation may exist anyway
            throw new HyperswitchUncertainException('Hyperswitch a rencontré une erreur : l\'opération est à vérifier.');
        }

        if ($response->status() === 401) {
            throw new ApiException('Clé API Hyperswitch absente ou incorrecte.', 502);
        }

        if ($response->failed()) {
            $message = $body['error']['reason'] ?? $body['error']['message'] ?? 'Erreur inconnue';
            $code = $body['error']['code'] ?? null;

            throw new ApiException(
                "{$refusedMessage} : {$message}".($code ? " ({$code})" : ''),
                $response->status() === 404 ? 404 : 502,
                $code ? ['code' => $code] : null,
            );
        }

        return $body;
    }
}
