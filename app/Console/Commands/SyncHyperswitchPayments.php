<?php

namespace App\Console\Commands;

use App\Services\PaymentSyncService;
use Illuminate\Console\Command;

/**
 * No webhook: follows the pending payment links by asking Hyperswitch,
 * each one at its own pace (PaymentSyncSchedule), until a final status.
 */
class SyncHyperswitchPayments extends Command
{
    protected $signature = 'payments:sync-hyperswitch {--limit=50 : Maximum sessions per run}';

    protected $description = 'Sync the status of the pending Hyperswitch payment links that are due';

    public function handle(PaymentSyncService $sync): int
    {
        $count = $sync->syncDueSessions((int) $this->option('limit'));

        $this->info("{$count} payment session(s) synced.");

        return self::SUCCESS;
    }
}
