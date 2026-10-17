<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class DvcTypeResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'insurance_type' => $this->insurance_type->value,
            'insurance_type_label' => $this->insurance_type->label(),
            'template_key' => $this->templateKey(),
            'label' => $this->label,
            'filename_prefix' => $this->filename_prefix,
            'is_active' => $this->is_active,
            'versions_count' => $this->whenCounted('versions'),
            'current_version' => $this->whenLoaded('currentVersion', fn () => $this->currentVersion
                ? new DvcTypeVersionResource($this->currentVersion)
                : null),
            'versions' => $this->whenLoaded('versions', fn () => $this->versions
                ->map(fn ($version) => (new DvcTypeVersionResource($version))->withoutSchema())
                ->all()),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
