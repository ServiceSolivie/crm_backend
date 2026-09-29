<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * When to ask Hyperswitch again about a pending payment (no webhook).
 *
 * Two cases, and nothing more:
 *  - the client came back to the result page: one NORMAL read on return,
 *    then one FORCED sync (force_sync calls Sogecommerce) 30 s later;
 *  - the client never came back (e.g. refused on the bank's page): NORMAL
 *    reads (Hyperswitch's own state) every 15 s during the first 10 minutes
 *    of the link.
 * Everything also stops at a final status. After that the agent can still
 * refresh by hand, and a new link always checks the previous one first.
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
}
