<?php

namespace App\Events;

use App\Models\PaymentSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A payment request of a lead changed (paid, failed, expired…): the lead
 * page listening on "leads.{id}" reloads its payments.
 *
 * Broadcast now (not queued): there is no queue worker on this hosting,
 * like the notifications (see LeadReceivedNotification::toBroadcast()).
 */
class PaymentSessionUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public PaymentSession $session) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('leads.'.$this->session->lead_id);
    }

    public function broadcastAs(): string
    {
        return 'payment-session.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->session->id,
            'reference' => $this->session->reference,
            'status' => $this->session->status->value,
            'lead_id' => $this->session->lead_id,
        ];
    }
}
