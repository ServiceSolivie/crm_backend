<?php

namespace App\Http\Resources;

use App\Services\ReportService;
use Illuminate\Http\Request;

class TeamReportResource extends BaseResource
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
            'is_active' => $this->is_active,
            'manager' => $this->whenLoaded('manager', fn () => $this->manager ? [
                'id' => $this->manager->id,
                'name' => $this->manager->name,
            ] : null),
            'members_count' => (int) $this->members_count,
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
                'total' => (int) $this->total_calls,
            ],
            // Supervisors see their team's figures without the money
            'revenue' => $this->when(! app(ReportService::class)->hidesRevenue($request->user()), fn () => [
                'received' => round((float) $this->revenue_received, 2),
            ]),
        ];
    }
}
