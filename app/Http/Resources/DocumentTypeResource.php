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
            // System type (signed DVC): always required, locked in the admin
            'is_system' => $this->resource->isSystem(),
            'requirements_count' => $this->whenCounted('requirements'),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
