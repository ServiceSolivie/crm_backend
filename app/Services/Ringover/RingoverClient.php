<?php

namespace App\Services\Ringover;

use App\Exceptions\RingoverException;
use App\Support\PhoneNumber;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Ringover public API (v2).
 *
 * Every call to Ringover goes through this class so the API key, error
 * handling and response shapes live in one place.
 */
class RingoverClient
{
    protected const USERS_CACHE_KEY = 'ringover.users';

    protected const USERS_CACHE_TTL = 300;

    public function isConfigured(): bool
    {
        return filled(config('services.ringover.api_key'));
    }

    /**
     * Ringover users of the company account, normalised:
     * [['user_id' => '123', 'firstname' => …, 'lastname' => …, 'email' => …, 'numbers' => ['+33…']], …]
     *
     * @return array<int, array<string, mixed>>
     */
    public function users(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::USERS_CACHE_KEY);
        }

        return Cache::remember(self::USERS_CACHE_KEY, self::USERS_CACHE_TTL, function () {
            $body = $this->get('/users');

            return collect($body['list'] ?? [])
                ->map(fn (array $user) => $this->normaliseUser($user))
                ->values()
                ->all();
        });
    }

    public function findUser(string $ringoverUserId): ?array
    {
        return collect($this->users())->firstWhere('user_id', $ringoverUserId);
    }

    /**
     * Check that the API key is valid. Returns the number of Ringover users visible.
     */
    public function testConnection(): int
    {
        return count($this->users(fresh: true));
    }

    /**
     * Calls started between two dates, one page at a time (GET /v2/calls).
     *
     * @return array<int, array<string, mixed>>
     */
    public function calls(CarbonInterface $from, CarbonInterface $to, int $limit = 500, int $offset = 0): array
    {
        $body = $this->get('/calls', [
            'start_date' => $from->copy()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'end_date' => $to->copy()->utc()->format('Y-m-d\TH:i:s.v\Z'),
            'limit_count' => $limit,
            'limit_offset' => $offset,
        ]);

        return $body['call_list'] ?? [];
    }

    /**
     * Ask Ringover to ring the agent's own devices (app, mobile…) first and,
     * once they pick up, call the lead (POST /v2/callback).
     */
    public function requestCallback(string $fromE164, string $toE164): array
    {
        // A single attempt: retrying after a server error could ring the agent twice.
        return $this->send(fn (PendingRequest $http) => $http->retry(1)->post('/callback', [
            'from_number' => (int) PhoneNumber::toRingover($fromE164),
            'to_number' => (int) PhoneNumber::toRingover($toE164),
            'timeout' => (int) config('services.ringover.callback.timeout', 20),
            'device' => config('services.ringover.callback.device', 'ALL'),
        ]));
    }

    /**
     * Download a recording, voicemail or transcription from Ringover.
     * Only Ringover hosts are accepted: the URL comes from a webhook and
     * must never make the server fetch anything else.
     */
    public function fetchMedia(string $url): Response
    {
        if (! self::isRingoverUrl($url)) {
            throw new RingoverException('Lien Ringover invalide.', 422);
        }

        try {
            $response = Http::withHeaders(['Authorization' => (string) config('services.ringover.api_key')])
                ->timeout(30)
                ->get($url);
        } catch (ConnectionException $e) {
            throw RingoverException::unreachable($e->getMessage());
        }

        if ($response->failed()) {
            throw RingoverException::requestFailed($response->status());
        }

        return $response;
    }

    public static function isRingoverUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');

        return ($parts['scheme'] ?? '') === 'https'
            && ($host === 'ringover.com' || str_ends_with($host, '.ringover.com'));
    }

    protected function normaliseUser(array $user): array
    {
        return [
            'user_id' => (string) ($user['user_id'] ?? ''),
            'firstname' => $user['firstname'] ?? null,
            'lastname' => $user['lastname'] ?? null,
            'email' => isset($user['email']) ? mb_strtolower(trim($user['email'])) : null,
            'numbers' => collect($user['numbers'] ?? [])
                ->map(fn ($n) => PhoneNumber::fromRingover(is_array($n) ? ($n['number'] ?? null) : $n))
                ->filter()
                ->unique()
                ->values()
                ->all(),
        ];
    }

    protected function get(string $path, array $query = []): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get($path, $query));
    }

    protected function send(callable $request): array
    {
        if (! $this->isConfigured()) {
            throw RingoverException::notConfigured();
        }

        try {
            /** @var Response $response */
            $response = $request($this->http());
        } catch (ConnectionException $e) {
            throw RingoverException::unreachable($e->getMessage());
        }

        if ($response->failed()) {
            throw RingoverException::requestFailed($response->status(), $response->json('message') ?? $response->body());
        }

        // Ringover call ids exceed PHP's integer precision: keep big numbers as strings.
        return json_decode($response->body(), true, 512, JSON_BIGINT_AS_STRING) ?? [];
    }

    protected function http(): PendingRequest
    {
        // Ringover expects the raw API key in the Authorization header (no "Bearer").
        return Http::baseUrl(rtrim(config('services.ringover.base_url'), '/'))
            ->withHeaders(['Authorization' => config('services.ringover.api_key')])
            ->acceptJson()
            ->asJson()
            ->timeout(config('services.ringover.timeout', 10))
            // Retry only transient failures (network errors, 5xx), never auth or validation errors.
            ->retry(2, 200, fn (\Throwable $e) => $e instanceof ConnectionException
                || ($e instanceof RequestException && $e->response->serverError()), throw: false);
    }
}
