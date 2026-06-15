<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AppointmentReminderResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'appointment_id' => $this->appointment_id,
            'remind_at' => $this->formatDate($this->remind_at),
            'channel' => $this->channel->value,
            'channel_label' => $this->channel->label(),
            'sent_at' => $this->formatDate($this->sent_at),
            'is_sent' => $this->sent_at !== null,
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
