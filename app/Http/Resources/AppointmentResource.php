<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AppointmentResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheduled_at' => $this->formatDate($this->scheduled_at),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'location' => $this->location,
            'notes' => $this->notes,
            'lead' => $this->whenLoaded('lead', fn () => [
                'id' => $this->lead->id,
                'reference' => $this->lead->reference,
                'first_name' => $this->lead->first_name,
                'last_name' => $this->lead->last_name,
                'phone' => $this->lead->phone,
                'insurance_type' => $this->lead->insurance_type->value
            ]),
            'agent' => $this->whenLoaded('agent', fn () => [
                'id' => $this->agent->id,
                'name' => $this->agent->name,
                'email' => $this->agent->email,
            ]),
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
