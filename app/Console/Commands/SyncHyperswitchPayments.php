<?php

namespace App\Console\Commands;

use App\Services\PaymentRefundService;
use App\Services\PaymentSyncService;
use Illuminate\Console\Command;

/**
 * No webhook: follows the pending payment links, then the pending refunds,
 * by asking Hyperswitch, each one at its own pace (PaymentSyncSchedule,
 * RefundSyncSchedule), until a final status.
 */
class SyncHyperswitchPayments extends Command
{
    protected $signature = 'payments:sync-hyperswitch {--limit=50 : Maximum sessions (and refunds) per run}';

    protected $description = 'Sync the status of the pending Hyperswitch payment links and refunds that are due';

    public function handle(PaymentSyncService $sync, PaymentRefundService $refunds): int
    {
        $limit = (int) $this->option('limit');

        $sessions = $sync->syncDueSessions($limit);
        $refunded = $refunds->syncDue($limit);

        $this->info("{$sessions} payment session(s) and {$refunded} refund(s) synced.");

        return self::SUCCESS;
    }
}
