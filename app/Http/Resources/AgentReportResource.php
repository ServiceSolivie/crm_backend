<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AgentReportResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totalLeads = (int) $this->total_leads;
        $validatedLeads = (int) $this->validated_leads;
        $totalAppointments = (int) $this->total_appointments;
        $completedAppointments = (int) $this->completed_appointments;
        $totalCalls = (int) $this->total_calls;
        $answeredCalls = (int) $this->answered_calls;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'team' => $this->whenLoaded('team', fn () => $this->team ? [
                'id' => $this->team->id,
                'name' => $this->team->name,
            ] : null),
            'leads' => [
                'total' => $totalLeads,
                'validated' => $validatedLeads,
                'conversion_rate' => $totalLeads > 0 ? round(($validatedLeads / $totalLeads) * 100, 2) : 0.0,
            ],
            'appointments' => [
                'total' => $totalAppointments,
                'completed' => $completedAppointments,
                'completion_rate' => $totalAppointments > 0 ? round(($completedAppointments / $totalAppointments) * 100, 2) : 0.0,
            ],
            'calls' => [
                'total' => $totalCalls,
                'answered' => $answeredCalls,
                'answer_rate' => $totalCalls > 0 ? round(($answeredCalls / $totalCalls) * 100, 2) : 0.0,
                'talk_seconds' => (int) $this->talk_seconds,
                // How many calls it takes to validate one lead (null: no validated lead yet).
                'per_validated_lead' => $validatedLeads > 0 ? round($totalCalls / $validatedLeads, 1) : null,
            ],
        ];
    }
}
