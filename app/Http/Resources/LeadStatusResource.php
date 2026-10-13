<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class LeadStatusResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'label' => $this->label,
            'stage' => $this->stage,
            'is_active' => $this->is_active,
            'leads_count' => $this->whenCounted('leads'),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
