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
