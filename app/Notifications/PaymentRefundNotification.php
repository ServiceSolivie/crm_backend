<?php

namespace App\Notifications;

use App\Enums\PaymentRefundStatusEnum;
use App\Models\PaymentRefund;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * Final result of a refund (done or failed), for the user who asked it —
 * a credit can take a day or more to be settled by the bank. Uses the
 * `leads` payload key so the bell's click-through opens the lead (see
 * PaymentResultNotification).
 */
class PaymentRefundNotification extends Notification
{
    public function __construct(protected PaymentRefund $refund) {}

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
        $session = $this->refund->paymentSession;
        $lead = $session->lead;
        $title = $this->refund->status === PaymentRefundStatusEnum::REUSSI ? 'Refund completed' : 'Refund failed';

        return [
            'title' => $title.' — '.$session->reference,
            'leads' => [[
                'id' => $lead->id,
                'name' => trim(($lead->first_name ?? '').' '.($lead->last_name ?? '')) ?: null,
                'source' => number_format((float) $this->refund->amount, 2, ',', ' ').' '.$this->refund->currency,
            ]],
            'refund' => [
                'refund_id' => $this->refund->id,
                'session_id' => $session->id,
                'reference' => $session->reference,
                'amount' => $this->refund->amount,
                'currency' => $this->refund->currency,
                'status' => $this->refund->status->value,
                'error_message' => $this->refund->error_message,
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
