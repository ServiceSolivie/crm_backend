<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class InsuranceTypeResource extends BaseResource
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
            'short_label' => $this->short_label,
            'lead_page_id' => $this->lead_page_id,
            'is_active' => $this->is_active,
            'requires_company_info' => $this->requires_company_info,
            'is_system' => $this->is_system,
            'sort_order' => $this->sort_order,
            'leads_count' => $this->whenCounted('leads'),
        ];
    }
}
