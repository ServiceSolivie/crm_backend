<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * The public page the client lands on after paying (Hyperswitch return_url).
 * It only shows the status of the payment:
 *  - its URL carries a 48-character random token; only the SHA-256 hash is
 *    stored, so the database alone can't rebuild a working URL;
 *  - it stays usable until the client comes back, then for a limited time
 *    (config services.hyperswitch.result_page_ttl, minutes).
 */
final class PaymentResultPage
{
    public static function generateToken(): string
    {
        return Str::random(48);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function url(string $baseUrl, string $token): string
    {
        return rtrim($baseUrl, '/').'/paiement/'.$token;
    }

    public static function isExpired(?CarbonInterface $returnedAt, CarbonInterface $now, int $ttlMinutes): bool
    {
        return $returnedAt !== null && $returnedAt->copy()->addMinutes($ttlMinutes)->lessThanOrEqualTo($now);
    }
}
