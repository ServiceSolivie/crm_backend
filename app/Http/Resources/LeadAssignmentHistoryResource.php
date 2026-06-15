<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class LeadAssignmentHistoryResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_user' => $this->whenLoaded('fromUser', fn () => $this->fromUser ? [
                'id' => $this->fromUser->id,
                'name' => $this->fromUser->name,
            ] : null),
            'to_user' => $this->whenLoaded('toUser', fn () => [
                'id' => $this->toUser->id,
                'name' => $this->toUser->name,
            ]),
            'assigned_by' => $this->whenLoaded('assignedBy', fn () => [
                'id' => $this->assignedBy->id,
                'name' => $this->assignedBy->name,
            ]),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
