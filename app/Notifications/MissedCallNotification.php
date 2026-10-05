<?php

namespace App\Notifications;

use App\Enums\CallStatusEnum;
use App\Models\Call;
use Illuminate\Notifications\Notification;

/**
 * A lead called and nobody answered: tell the agent the lead is assigned to.
 */
class MissedCallNotification extends Notification
{
    public function __construct(public Call $call) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $lead = $this->call->lead;
        $name = trim(($lead?->first_name ?? '').' '.($lead?->last_name ?? ''));
        $voicemail = $this->call->status === CallStatusEnum::VOICEMAIL;

        return [
            'kind' => 'missed_call',
            'title' => $voicemail ? 'Message vocal de '.$name : 'Appel manqué de '.$name,
            'body' => $this->call->contact_number,
            'call_id' => $this->call->id,
            'lead_id' => $lead?->id,
            'lead_reference' => $lead?->reference,
            'voicemail' => $voicemail,
            'occurred_at' => ($this->call->started_at ?? now())->toIso8601String(),
            'url' => $lead ? '/leads/'.$lead->id : null,
        ];
    }
}
