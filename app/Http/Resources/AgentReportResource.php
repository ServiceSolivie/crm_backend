<?php

namespace App\Http\Resources;

use App\Services\ReportService;
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
        $totalPhoneCalls = (int) $this->total_phone_calls;
        $answeredPhoneCalls = (int) $this->answered_phone_calls;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
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
                'total' => $totalPhoneCalls,
            ],
            // Calls made or received through Ringover.
            'phone_calls' => [
                'total' => $totalPhoneCalls,
                'answered' => $answeredPhoneCalls,
                'answer_rate' => $totalPhoneCalls > 0 ? round(($answeredPhoneCalls / $totalPhoneCalls) * 100, 2) : 0.0,
                'talk_seconds' => (int) $this->talk_seconds,
                // How many calls it takes to validate one lead (null: no validated lead yet).
                'per_validated_lead' => $validatedLeads > 0 ? round($totalPhoneCalls / $validatedLeads, 1) : null,
            ],
            // Supervisors see their team's figures without the money
            'revenue' => $this->when(! app(ReportService::class)->hidesRevenue($request->user()), fn () => [
                'received' => round((float) $this->revenue_received, 2),
            ]),
        ];
    }
}
