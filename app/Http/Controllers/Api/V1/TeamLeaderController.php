<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LeadStatusEnum;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamLeaderController extends Controller
{
    public function agents(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->team_id, 403, 'You are not assigned to a team.');
        abort_unless(
            $user->can(PermissionEnum::DASHBOARD_VIEW_TEAM->value),
            403,
            'Insufficient permissions.',
        );

        $teamId = $user->team_id;

        $agents = User::where('team_id', $teamId)
            ->where('id', '!=', $user->id)
            ->with('roles')
            ->withCount([
                'assignedLeads as active_leads_count' => fn ($q) => $q->where('team_id', $teamId)
                    ->whereNotIn('status', [LeadStatusEnum::PAS_INTERESSEE->value]),
                'assignedLeads as validated_leads_count' => fn ($q) => $q->where('team_id', $teamId)
                    ->where('status', LeadStatusEnum::VALIDE->value),
                'assignedLeads as total_leads_count' => fn ($q) => $q->where('team_id', $teamId),
                'appointments as scheduled_appointments_count' => fn ($q) => $q->where('status', 'PLANIFIE'),
                'appointments as completed_appointments_count' => fn ($q) => $q->where('status', 'REALISE'),
            ])
            ->withSum(['assignedLeads as total_revenue' => fn ($q) => $q->where('team_id', $teamId)
                ->where('status', LeadStatusEnum::VALIDE->value),
            ], 'expected_revenue')
            ->orderBy('name')
            ->get();

        $result = $agents->map(fn (User $agent) => [
            'id' => $agent->id,
            'name' => $agent->name,
            'email' => $agent->email,
            'roles' => $agent->getRoleNames(),
            'active_leads' => $agent->active_leads_count ?? 0,
            'validated_leads' => $agent->validated_leads_count ?? 0,
            'total_leads' => $agent->total_leads_count ?? 0,
            'conversion_rate' => ($agent->total_leads_count ?? 0) > 0
                ? round(($agent->validated_leads_count ?? 0) / $agent->total_leads_count * 100, 1)
                : 0,
            'scheduled_appointments' => $agent->scheduled_appointments_count ?? 0,
            'completed_appointments' => $agent->completed_appointments_count ?? 0,
            'total_revenue' => (float) ($agent->total_revenue ?? 0),
        ]);

        return $this->success($result);
    }

    public function followUps(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->team_id, 403, 'You are not assigned to a team.');
        abort_unless(
            $user->can(PermissionEnum::LEADS_VIEW_TEAM->value),
            403,
            'Insufficient permissions.',
        );

        $teamId = $user->team_id;
        $now = Carbon::now();

        $leads = Lead::where('team_id', $teamId)
            ->whereIn('status', [
                LeadStatusEnum::NRP->value,
                LeadStatusEnum::RAPPEL->value,
                LeadStatusEnum::RENDEZ_VOUS_ASSURE->value,
            ])
            ->with(['assignedAgent:id,name', 'team:id,name'])
            ->get();

        $followUps = $leads->map(function (Lead $lead) use ($now) {
            $lastActivity = $lead->updated_at;
            $hoursIdle = $lastActivity ? $now->diffInHours($lastActivity) : 999;

            $urgency = match (true) {
                $hoursIdle > 48 => 'overdue',
                $hoursIdle > 24 => 'warning',
                $hoursIdle > 12 => 'due_today',
                default => 'on_track',
            };

            $reason = null;
            if ($lead->status === LeadStatusEnum::NRP->value && $hoursIdle > 24) {
                $reason = 'NRP with no activity for ' . round($hoursIdle) . 'h';
            } elseif ($lead->status === LeadStatusEnum::RAPPEL->value && $hoursIdle > 48) {
                $reason = 'Follow-up overdue by ' . round($hoursIdle - 48) . 'h';
            } elseif ($lead->status === LeadStatusEnum::RENDEZ_VOUS_ASSURE->value) {
                $hasAppointment = Appointment::where('lead_id', $lead->id)
                    ->where('status', 'PLANIFIE')
                    ->exists();
                if (! $hasAppointment) {
                    $reason = 'RDV status but no scheduled appointment';
                    $urgency = 'warning';
                }
            }

            return [
                'id' => $lead->id,
                'reference' => $lead->reference,
                'name' => trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? '')),
                'status' => $lead->status,
                'assigned_agent' => $lead->assignedAgent ? [
                    'id' => $lead->assignedAgent->id,
                    'name' => $lead->assignedAgent->name,
                ] : null,
                'last_activity' => $lead->updated_at?->toIso8601String(),
                'hours_idle' => round($hoursIdle),
                'urgency' => $urgency,
                'reason' => $reason,
            ];
        })
            ->sortBy(fn ($item) => match ($item['urgency']) {
                'overdue' => 0,
                'warning' => 1,
                'due_today' => 2,
                default => 3,
            })
            ->values();

        $summary = [
            'overdue' => $followUps->where('urgency', 'overdue')->count(),
            'warning' => $followUps->where('urgency', 'warning')->count(),
            'due_today' => $followUps->where('urgency', 'due_today')->count(),
            'total' => $followUps->count(),
        ];

        return $this->success([
            'summary' => $summary,
            'items' => $followUps,
        ]);
    }
}
