<?php

namespace App\Services\Ringover;

use App\Models\RingoverWebhookEvent;
use Illuminate\Database\QueryException;
use Throwable;

class RingoverWebhookHandler
{
    public function __construct(
        protected RingoverCallMapper $mapper,
        protected CallRecorder $recorder,
    ) {}

    /**
     * Store an incoming webhook. Returns null when it is a repeat delivery
     * of an event already stored (nothing more to do).
     */
    public function store(array $payload): ?RingoverWebhookEvent
    {
        $key = RingoverWebhookEvent::dedupeKeyFor($payload);

        $existing = RingoverWebhookEvent::where('dedupe_key', $key)->first();
        if ($existing) {
            $existing->increment('deliveries');

            return null;
        }

        try {
            return RingoverWebhookEvent::create([
                'event' => (string) ($payload['event'] ?? 'unknown'),
                'ringover_call_id' => isset($payload['data']['call_id']) ? (string) $payload['data']['call_id'] : null,
                'dedupe_key' => $key,
                'payload' => $payload,
            ]);
        } catch (QueryException $e) {
            // The same event was stored by a concurrent delivery.
            if (str_contains($e->getMessage(), 'dedupe_key')) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Apply a stored event to the calls table. Safe to run more than once.
     */
    public function process(RingoverWebhookEvent $event): void
    {
        if ($event->processed_at !== null) {
            return;
        }

        try {
            $data = $this->mapper->fromWebhook($event->event, $event->payload['data'] ?? []);

            if ($data !== null) {
                $this->recorder->record($data, 'webhook', $event->event);
            }

            $event->update(['processed_at' => now(), 'error' => $data === null ? 'ignored' : null]);
        } catch (Throwable $e) {
            $event->update(['error' => mb_substr($e->getMessage(), 0, 2000)]);

            throw $e;
        }
    }
}
