<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PaymentResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->amount,
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'source' => $this->source?->value,
            'source_label' => $this->source?->label(),
            'external_id' => $this->external_id,
            'failure_reason' => $this->failure_reason,
            'status_changed_at' => $this->formatDate($this->status_changed_at),
            'status_changed_by' => $this->whenLoaded('statusChanger', fn () => $this->statusChanger ? [
                'id' => $this->statusChanger->id,
                'name' => $this->statusChanger->name,
            ] : null),
            'payment_date' => $this->payment_date->toDateString(),
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'custom_payment_method' => $this->custom_payment_method,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
