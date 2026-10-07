<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ActivityLogResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event->value,
            'event_label' => $this->event->label(),
            'category' => $this->category->value,
            'level' => $this->level->value,
            'level_label' => $this->level->label(),
            'message' => $this->message,
            'actor' => [
                'type' => $this->actor_type,
                'id' => $this->actor_id,
                'name' => $this->relationLoaded('actor') ? $this->actor?->name : null,
            ],
            'properties' => $this->properties ?? (object) [],
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
