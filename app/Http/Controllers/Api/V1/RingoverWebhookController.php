<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRingoverWebhook;
use App\Services\Ringover\RingoverWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives Ringover webhooks. Answers immediately and processes in the
 * background, so Ringover never times out and retries.
 */
class RingoverWebhookController extends Controller
{
    public function __construct(protected RingoverWebhookHandler $handler) {}

    public function __invoke(Request $request): JsonResponse
    {
        // Ringover call ids exceed PHP's integer precision: keep big numbers as strings.
        $payload = json_decode($request->getContent(), true, 512, JSON_BIGINT_AS_STRING);

        if (! is_array($payload) || empty($payload['event'])) {
            return $this->error('Invalid payload.', 400);
        }

        $event = $this->handler->store($payload);

        if ($event === null) {
            return $this->success(null, 'Duplicate delivery ignored');
        }

        ProcessRingoverWebhook::dispatch($event->id);

        return $this->success(null, 'Received');
    }
}
