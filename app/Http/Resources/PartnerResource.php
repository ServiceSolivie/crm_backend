<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PartnerResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'login_url' => $this->login_url,
            'domain' => $this->domain,
            'field_mapping' => $this->field_mapping,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'credentials_count' => $this->whenCounted('credentials'),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
