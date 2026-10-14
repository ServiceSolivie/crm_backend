<?php

namespace App\Console\Commands;

use App\Services\ActivityJournalService;
use Illuminate\Console\Command;

/**
 * Keeps the activity journal from growing forever: deletes the operations
 * with no activity for longer than the retention, with their logs.
 */
class PruneActivityLogs extends Command
{
    protected $signature = 'activity-logs:prune {--days= : Keep this many days (default: config activity_log.retention_days)}';

    protected $description = 'Delete the activity journal operations older than the retention period';

    public function handle(ActivityJournalService $journal): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;

        $deleted = $journal->prune($days);

        $this->info("{$deleted} operation(s) deleted from the activity journal.");

        return self::SUCCESS;
    }
}
