<?php

namespace App\Support;

use App\Enums\PaymentSessionStatusEnum;
use Carbon\CarbonInterface;

/**
 * When to ask Hyperswitch again about a pending payment (no webhook).
 *
 *  - the client came back to the result page: one NORMAL read on return,
 *    then one FORCED sync (force_sync calls Sogecommerce) 30 s later;
 *  - the client never came back (e.g. refused on the bank's page): NORMAL
 *    reads (Hyperswitch's own state) every 15 s during the first 10 minutes
 *    of the link;
 *  - in both cases, if still not final: one FORCED check just after the
 *    link's expiry (15 min), which closes it (paid, refused or expired).
 * Everything stops at a final status. The agent can still refresh by hand,
 * and a new link always checks the previous one first.
 */
final class PaymentSyncSchedule
{
    /** Forced syncs, in seconds AFTER THE CLIENT'S RETURN */
    public const AFTER_RETURN_FORCED = [30];

    /** Normal reads while the client hasn't come back: every … seconds */
    public const NORMAL_INTERVAL = 15;

    /** … during the first … seconds of the link */
    public const NORMAL_WINDOW = 600;

    /**
     * The job runs every 15 s (bootstrap/app.php) and needs a few seconds to
     * start, so a read due a few seconds after a run would wait for the next
     * one and the interval would double. A normal read due within this many
     * seconds is taken by the current run. Forced syncs keep their exact time.
     */
    public const DUE_TOLERANCE = 10;

    /** Minimum time between two forced syncs asked by a user */
    public const USER_FORCED_MIN_INTERVAL = 60;

    /**
     * The Hyperswitch link expires (15 min, its "expires_on"); Hyperswitch
     * then still says "waiting for the client". One forced check this many
     * seconds after the expiry closes the link (paid, refused or expired).
     */
    public const EXPIRY_GRACE = 60;

    /** Past the expiry, no answer yet: retry every … seconds, for … seconds */
    public const EXPIRY_RETRY_INTERVAL = 60;

    public const EXPIRY_RETRY_WINDOW = 180;

    /** Past the expiry but the payment is being processed: every …, for … */
    public const EXPIRED_PROCESSING_INTERVAL = 300;

    public const EXPIRED_PROCESSING_WINDOW = 3600;

    /**
     * When the next forced sync is due after the client's return, or null
     * when it has been done.
     */
    public static function nextForcedAt(CarbonInterface $returnedAt, int $forcedDone): ?CarbonInterface
    {
        $offset = self::AFTER_RETURN_FORCED[$forcedDone] ?? null;

        return $offset === null ? null : $returnedAt->copy()->addSeconds($offset);
    }

    /** Delay before the very first normal read of a new link */
    public static function firstNormalDelay(): int
    {
        return self::NORMAL_INTERVAL;
    }

    /**
     * Seconds until the next normal read, or null to stop following the
     * link (older than NORMAL_WINDOW).
     */
    public static function nextNormalDelay(CarbonInterface $createdAt, CarbonInterface $now): ?int
    {
        return $createdAt->diffInSeconds($now, true) < self::NORMAL_WINDOW ? self::NORMAL_INTERVAL : null;
    }

    /** When the expiry check is due (expiry + grace), or null if unknown */
    public static function expiryCheckAt(?CarbonInterface $expiresAt): ?CarbonInterface
    {
        return $expiresAt?->copy()->addSeconds(self::EXPIRY_GRACE);
    }

    public static function isPastExpiry(?CarbonInterface $expiresAt, CarbonInterface $now): bool
    {
        $checkAt = self::expiryCheckAt($expiresAt);

        return $checkAt !== null && $now->greaterThanOrEqualTo($checkAt);
    }

    /**
     * Next (forced) check linked to the expiry, once the other checks are
     * over: the expiry check itself, then — past it, still not final — a
     * few retries (no answer) or a slower follow-up (payment processing).
     * Null: stop (the agent can still refresh by hand).
     */
    public static function nextExpiryCheck(?CarbonInterface $expiresAt, ?string $providerStatus, CarbonInterface $now): ?CarbonInterface
    {
        $checkAt = self::expiryCheckAt($expiresAt);
        if ($checkAt === null) {
            return null;
        }

        if ($now->lessThan($checkAt)) {
            return $checkAt;
        }

        [$interval, $window] = PaymentSessionStatusEnum::providerIsProcessing($providerStatus)
            ? [self::EXPIRED_PROCESSING_INTERVAL, self::EXPIRED_PROCESSING_WINDOW]
            : [self::EXPIRY_RETRY_INTERVAL, self::EXPIRY_RETRY_WINDOW];

        return $now->lessThan($checkAt->copy()->addSeconds($window))
            ? $now->copy()->addSeconds($interval)
            : null;
    }
}
