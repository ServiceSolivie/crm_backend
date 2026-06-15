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
        ];
    }
}
