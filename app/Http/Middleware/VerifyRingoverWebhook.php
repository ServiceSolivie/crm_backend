<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects webhook calls that do not come from Ringover.
 *
 * Ringover can authenticate webhooks in two ways (see its official SDK):
 *  - a JWT signed with the webhook secret (HS512) in "x-ringover-webhook-signature";
 *  - the secret itself, in that header or as "Authorization: Bearer base64(secret)".
 */
class VerifyRingoverWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.ringover.webhook_secret');

        if ($secret === '') {
            Log::warning('Ringover webhook rejected: RINGOVER_WEBHOOK_SECRET is not set.');

            return ApiResponse::error('Webhook not configured.', 503);
        }

        if (! $this->isAuthentic($request, $secret)) {
            return ApiResponse::error('Invalid webhook signature.', 401);
        }

        return $next($request);
    }

    protected function isAuthentic(Request $request, string $secret): bool
    {
        $signature = (string) $request->header('x-ringover-webhook-signature', '');

        if ($signature !== '') {
            return substr_count($signature, '.') === 2
                ? $this->isValidJwt($signature, $secret)
                : hash_equals($secret, $signature);
        }

        $authorization = (string) $request->header('Authorization', '');

        return $authorization !== '' && hash_equals('Bearer '.base64_encode($secret), $authorization);
    }

    protected function isValidJwt(string $jwt, string $secret): bool
    {
        [$header64, $payload64, $signature64] = explode('.', $jwt);

        $header = json_decode($this->base64UrlDecode($header64), true);
        if (($header['alg'] ?? null) !== 'HS512') {
            return false;
        }

        $expected = hash_hmac('sha512', $header64.'.'.$payload64, $secret, true);
        if (! hash_equals($expected, $this->base64UrlDecode($signature64))) {
            return false;
        }

        $claims = json_decode($this->base64UrlDecode($payload64), true) ?? [];

        // 60 s leeway for clock drift.
        return ! isset($claims['exp']) || (int) $claims['exp'] >= time() - 60;
    }

    protected function base64UrlDecode(string $value): string
    {
        return (string) base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4));
    }
}
