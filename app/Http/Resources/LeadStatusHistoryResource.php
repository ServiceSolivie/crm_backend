<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class LeadStatusHistoryResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status->value,
            'comment' => $this->comment,
            'changed_by' => $this->whenLoaded('changedBy', fn () => [
                'id' => $this->changedBy->id,
                'name' => $this->changedBy->name,
            ]),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
