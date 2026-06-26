<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class DocumentTypeResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'label' => $this->label,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
