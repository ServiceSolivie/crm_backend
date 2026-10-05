<?php

namespace App\Console\Commands;

use App\Models\RingoverSyncRun;
use App\Models\RingoverWebhookEvent;
use App\Services\Ringover\CallRecorder;
use App\Services\Ringover\RingoverCallMapper;
use App\Services\Ringover\RingoverClient;
use App\Services\Ringover\RingoverWebhookHandler;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Safety net for webhooks: pulls calls from Ringover to fill in anything
 * missed, and processes stored webhook events that were never handled
 * (e.g. no queue worker running).
 *
 * Each run starts where the last successful one ended, so after an outage
 * of any length (up to services.ringover.sync.max_days) the first run
 * catches everything up. A failed run does not move that point forward.
 */
class SyncRingoverCalls extends Command
{
    protected $signature = 'ringover:sync-calls
                            {--hours= : Also fetch at least this many hours back (manual catch-up)}';

    protected $description = 'Fetch calls from Ringover since the last successful sync and process pending webhook events';

    protected const PAGE_SIZE = 500;

    public function handle(
        RingoverClient $client,
        RingoverCallMapper $mapper,
        CallRecorder $recorder,
        RingoverWebhookHandler $webhooks,
    ): int {
        $pending = $this->processPendingWebhooks($webhooks);

        if (! $client->isConfigured()) {
            $this->warn('Ringover is not configured (RINGOVER_API_KEY); skipping call sync.');

            return self::SUCCESS;
        }

        $to = CarbonImmutable::now();
        $from = $this->windowStart($to);

        $run = RingoverSyncRun::create([
            'window_from' => $from,
            'window_to' => $to,
            'status' => 'running',
            'pending_webhooks_processed' => $pending,
            'started_at' => now(),
        ]);

        $this->line("Fetching Ringover calls from {$from->toDateTimeString()} to {$to->toDateTimeString()}…");

        $synced = 0;

        try {
            foreach ($this->slices($from, $to) as [$sliceFrom, $sliceTo]) {
                $offset = 0;

                do {
                    $page = $client->calls($sliceFrom, $sliceTo, self::PAGE_SIZE, $offset);

                    foreach ($page as $item) {
                        $data = $mapper->fromCallsApi($item);

                        if ($data !== null) {
                            $recorder->record($data, 'sync');
                            $synced++;
                        }
                    }

                    $offset += self::PAGE_SIZE;
                } while (count($page) === self::PAGE_SIZE);
            }
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'calls_synced' => $synced,
                'error' => mb_substr($e->getMessage(), 0, 2000),
                'completed_at' => now(),
            ]);

            $this->error('Sync failed: '.$e->getMessage().' (the next run will retry from the same point).');

            return self::FAILURE;
        }

        $run->update(['status' => 'success', 'calls_synced' => $synced, 'completed_at' => now()]);

        $this->info("{$synced} call(s) synced from Ringover.");

        return self::SUCCESS;
    }

    /**
     * Where this run starts: the end of the last successful sync (minus a
     * small overlap), or the configured initial window on the very first run.
     * --hours can only extend the window further back, never skip a gap.
     */
    protected function windowStart(CarbonImmutable $to): CarbonImmutable
    {
        $config = config('services.ringover.sync');
        $last = RingoverSyncRun::lastSuccessful();

        $from = $last
            ? CarbonImmutable::instance($last->window_to)->subMinutes($config['overlap_minutes'])
            : $to->subHours($config['initial_hours']);

        if ($hours = (int) $this->option('hours')) {
            $from = $from->min($to->subHours($hours));
        }

        $oldest = $to->subDays($config['max_days']);

        if ($from->lt($oldest)) {
            $this->warn("Last successful sync is older than {$config['max_days']} days; calls before {$oldest->toDateTimeString()} are not fetched.");
            $from = $oldest;
        }

        return $from;
    }

    /**
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    protected function slices(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $size = max(1, (int) config('services.ringover.sync.slice_hours'));
        $slices = [];

        for ($start = $from; $start->lt($to); $start = $end) {
            $end = $start->addHours($size)->min($to);
            $slices[] = [$start, $end];
        }

        return $slices;
    }

    /**
     * Events older than a few minutes that are still unprocessed were most
     * likely never picked up by a queue worker.
     */
    protected function processPendingWebhooks(RingoverWebhookHandler $webhooks): int
    {
        $processed = 0;
        $failed = 0;

        RingoverWebhookEvent::whereNull('processed_at')
            ->where('created_at', '<=', now()->subMinutes(5))
            ->orderBy('id')
            ->chunkById(200, function ($events) use ($webhooks, &$processed, &$failed) {
                foreach ($events as $event) {
                    try {
                        $webhooks->process($event);
                        $processed++;
                    } catch (Throwable) {
                        $failed++;
                    }
                }
            });

        if ($processed || $failed) {
            $this->info("{$processed} pending webhook event(s) processed, {$failed} failed.");
        }

        return $processed;
    }
}
