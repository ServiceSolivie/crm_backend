<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class NotificationResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => class_basename($this->type),
            'payload' => $this->data,
            'read_at' => $this->formatDate($this->read_at),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
