<?php

namespace App\Support;

use App\Enums\PaymentRefundStatusEnum;
use Carbon\CarbonInterface;

/**
 * When to ask Hyperswitch again about a refund that isn't final (no webhook).
 *
 *  - A_VERIFIER (unclear answer at creation): a NORMAL read every minute,
 *    a few times — Hyperswitch itself knows whether the refund exists;
 *  - EN_ATTENTE (credit waiting for the bank's settlement, usually the
 *    next business day, a weekend can make it three): FORCED syncs
 *    (Sogecommerce Transaction/Get), one minute after the request, then
 *    ever more spaced out (see TIERS) until a week after the request.
 * Then the automatic checks stop; the agent can still press "Vérifier".
 */
final class RefundSyncSchedule
{
    /** A_VERIFIER: one normal read every … seconds, at most … times */
    public const CHECK_INTERVAL = 60;

    public const CHECK_MAX_ATTEMPTS = 10;

    /** EN_ATTENTE: first forced check, this long after the request */
    public const FIRST_CHECK = 60;

    /**
     * EN_ATTENTE: while the refund is younger than [0] seconds, check every
     * [1] seconds. Past the last tier the automatic checks stop.
     *
     * @var list<array{int, int}>
     */
    public const TIERS = [
        [7200, 1800],     // 0 – 2 h: every 30 min
        [21600, 7200],    // 2 – 6 h: every 2 h
        [86400, 21600],   // 6 – 24 h: every 6 h
        [259200, 43200],  // 24 – 72 h: every 12 h
        [604800, 86400],  // 3 – 7 days: every 24 h
    ];

    /**
     * Next check of a refund still pending, or null to stop following it.
     */
    public static function nextCheckAt(PaymentRefundStatusEnum $status, int $attempts, CarbonInterface $requestedAt, CarbonInterface $now): ?CarbonInterface
    {
        if ($status === PaymentRefundStatusEnum::A_VERIFIER) {
            return $attempts < self::CHECK_MAX_ATTEMPTS ? $now->copy()->addSeconds(self::CHECK_INTERVAL) : null;
        }

        if ($status !== PaymentRefundStatusEnum::EN_ATTENTE) {
            return null;
        }

        $elapsed = $requestedAt->diffInSeconds($now, true);

        if ($elapsed < self::FIRST_CHECK) {
            return $requestedAt->copy()->addSeconds(self::FIRST_CHECK);
        }

        foreach (self::TIERS as [$until, $interval]) {
            if ($elapsed < $until) {
                return $now->copy()->addSeconds($interval);
            }
        }

        return null;
    }

    /**
     * A waiting credit is only settled at Sogecommerce: ask it (force_sync).
     * An unclear creation only needs Hyperswitch's own record.
     */
    public static function shouldForce(PaymentRefundStatusEnum $status): bool
    {
        return $status === PaymentRefundStatusEnum::EN_ATTENTE;
    }
}
