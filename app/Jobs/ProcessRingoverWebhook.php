<?php

namespace App\Jobs;

use App\Models\RingoverWebhookEvent;
use App\Services\Ringover\RingoverWebhookHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessRingoverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public int $eventId) {}

    public function handle(RingoverWebhookHandler $handler): void
    {
        $event = RingoverWebhookEvent::find($this->eventId);

        if ($event) {
            $handler->process($event);
        }
    }
}
