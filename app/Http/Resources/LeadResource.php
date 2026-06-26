<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class LeadResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'city' => $this->city,
            'birth_date' => $this->birth_date?->toDateString(),
            'insurance_type' => $this->insurance_type->value,
            'insurance_type_label' => $this->insurance_type->label(),
            'client_type' => $this->client_type?->value,
            'client_type_label' => $this->client_type?->label(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'comment' => $this->comment,
            'lead_source' => $this->whenLoaded('leadSource', fn () => [
                'id' => $this->leadSource->id,
                'name' => $this->leadSource->name,
                'code' => $this->leadSource->code,
            ]),
            'assigned_agent' => $this->whenLoaded('assignedAgent', fn () => $this->assignedAgent ? [
                'id' => $this->assignedAgent->id,
                'name' => $this->assignedAgent->name,
                'email' => $this->assignedAgent->email,
            ] : null),
            'team' => $this->whenLoaded('team', fn () => $this->team ? [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ] : null),
            'expected_revenue' => $this->expected_revenue,
            'total_received' => $this->when($this->expected_revenue !== null, fn () => $this->total_received),
            'remaining_amount' => $this->when($this->expected_revenue !== null, fn () => $this->remaining_amount),
            'payment_status' => $this->payment_status?->value,
            'payment_status_label' => $this->payment_status?->label(),
            'validated_at' => $this->formatDate($this->validated_at),
            'payments_count' => $this->whenCounted('payments'),
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
