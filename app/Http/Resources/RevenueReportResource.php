<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class RevenueReportResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $expected = (float) $this->expected_revenue;
        $received = (float) ($this->payments_sum_amount ?? 0);
        $remaining = round($expected - $received, 2);

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'phone' => $this->phone,
            'expected_revenue' => $expected,
            'total_received' => round($received, 2),
            'remaining_amount' => $remaining,
            'payment_status' => $this->payment_status?->value,
            'payment_status_label' => $this->payment_status?->label(),
            'payments_count' => (int) ($this->payments_count ?? 0),
            'validated_at' => $this->validated_at?->toDateString(),
            'agent' => $this->whenLoaded('assignedAgent', fn () => $this->assignedAgent ? [
                'id' => $this->assignedAgent->id,
                'name' => $this->assignedAgent->name,
            ] : null),
            'team' => $this->whenLoaded('team', fn () => $this->team ? [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ] : null),
        ];
    }
}
