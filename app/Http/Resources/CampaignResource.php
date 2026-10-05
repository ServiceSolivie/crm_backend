<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CampaignResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'external_campaign_id' => $this->external_campaign_id,
            'form_id' => $this->form_id, 'form_name' => $this->form_name,
            'insurance_type' => $this->insurance_type?->value, 'insurance_type_label' => $this->insurance_type?->label(),
            'is_active' => $this->is_active, 'leads_count' => $this->whenCounted('leads'), 'touchpoints_count' => $this->whenCounted('touchpoints'),
            'lead_source' => $this->whenLoaded('leadSource', fn () => ['id' => $this->leadSource->id, 'name' => $this->leadSource->name, 'code' => $this->leadSource->code]),
            'created_at' => $this->formatDate($this->created_at), 'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
