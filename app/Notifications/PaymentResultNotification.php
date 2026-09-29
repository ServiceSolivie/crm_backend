<?php

namespace App\Notifications;

use App\Enums\PaymentSessionStatusEnum;
use App\Models\PaymentSession;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Result of a payment link (paid or failed), for the agent who sent it and
 * the lead's assigned agent. Uses the `leads` payload key so the bell's
 * click-through opens the lead (see LeadReceivedNotification).
 */
class PaymentResultNotification extends Notification
{
    public function __construct(protected PaymentSession $session) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $lead = $this->session->lead;
        $paid = $this->session->status === PaymentSessionStatusEnum::PAYEE;

        return [
            'title' => ($paid ? 'Payment received' : 'Payment failed').' — '.$this->session->reference,
            'leads' => [[
                'id' => $lead->id,
                'name' => trim(($lead->first_name ?? '').' '.($lead->last_name ?? '')) ?: null,
                'source' => number_format((float) $this->session->amount, 2, ',', ' ').' '.$this->session->currency,
            ]],
            'payment' => [
                'session_id' => $this->session->id,
                'reference' => $this->session->reference,
                'amount' => $this->session->amount,
                'currency' => $this->session->currency,
                'status' => $this->session->status->value,
            ],
        ];
    }

    /**
     * Broadcast on the 'sync' connection (no queue worker on this hosting).
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))->onConnection('sync');
    }
}
