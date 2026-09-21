<?php

namespace App\Repositories\Eloquent;

use App\Enums\AppointmentStatusEnum;
use App\Enums\LeadStatusEnum;
use App\Enums\PaymentRecordStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\RoleEnum;
use App\Filters\AppointmentFilter;
use App\Filters\LeadFilter;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use App\Repositories\Contracts\ReportRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ReportRepository implements ReportRepositoryInterface
{
    public function paginateTeamReport(?Closure $scope, ?int $teamId, ?string $from, ?string $to, int $perPage): LengthAwarePaginator
    {
        $query = Team::query()
            ->with('manager')
            ->withCount('members')
            ->withCount(['leads as total_leads' => function (Builder $query) use ($from, $to) {
                $this->applyDateRange($query, 'created_at', $from, $to);
            }])
            ->withCount(['leads as validated_leads' => function (Builder $query) use ($from, $to) {
                $query->where('status', LeadStatusEnum::VALIDE->value);
                $this->applyDateRange($query, 'created_at', $from, $to);
            }])
            ->withCount(['appointments as total_appointments' => function (Builder $query) use ($from, $to) {
                $this->applyDateRange($query, 'scheduled_at', $from, $to);
            }])
            ->withCount(['appointments as completed_appointments' => function (Builder $query) use ($from, $to) {
                $query->where('appointments.status', AppointmentStatusEnum::REALISE->value);
                $this->applyDateRange($query, 'scheduled_at', $from, $to);
            }])
            ->withCount(['calls as total_calls' => function (Builder $query) use ($from, $to) {
                $this->applyDateRange($query, 'lead_calls.created_at', $from, $to);
            }])
            ->withSum(['payments as revenue_received' => function (Builder $query) use ($from, $to) {
                $query->where('payments.status', PaymentRecordStatusEnum::REUSSI->value);
                $this->applyDateRange($query, 'payments.payment_date', $from, $to);
            }], 'amount');

        if ($teamId) {
            $query->where('teams.id', $teamId);
        }

        if ($scope) {
            $scope($query);
        }

        return $query->paginate($perPage);
    }

    public function paginateAgentReport(?Closure $scope, ?int $teamId, ?string $from, ?string $to, int $perPage): LengthAwarePaginator
    {
        $query = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', RoleEnum::AGENT->value))
            ->with('team')
            ->withCount(['assignedLeads as total_leads' => function (Builder $query) use ($from, $to) {
                $this->applyDateRange($query, 'created_at', $from, $to);
            }])
            ->withCount(['assignedLeads as validated_leads' => function (Builder $query) use ($from, $to) {
                $query->where('status', LeadStatusEnum::VALIDE->value);
                $this->applyDateRange($query, 'created_at', $from, $to);
            }])
            ->withCount(['appointments as total_appointments' => function (Builder $query) use ($from, $to) {
                $this->applyDateRange($query, 'scheduled_at', $from, $to);
            }])
            ->withCount(['appointments as completed_appointments' => function (Builder $query) use ($from, $to) {
                $query->where('status', AppointmentStatusEnum::REALISE->value);
                $this->applyDateRange($query, 'scheduled_at', $from, $to);
            }])
            ->withCount(['calls as total_calls' => function (Builder $query) use ($from, $to) {
                $this->applyDateRange($query, 'created_at', $from, $to);
            }])
            ->withSum(['assignedLeadPayments as revenue_received' => function (Builder $query) use ($from, $to) {
                $query->where('payments.status', PaymentRecordStatusEnum::REUSSI->value);
                $this->applyDateRange($query, 'payments.payment_date', $from, $to);
            }], 'amount');

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        if ($scope) {
            $scope($query);
        }

        return $query->paginate($perPage);
    }

    public function paginateConversionReport(string $groupBy, ?Closure $scope, ?int $teamId, ?string $from, ?string $to, int $perPage): LengthAwarePaginator
    {
        $query = Lead::query()->select([]);

        match ($groupBy) {
            'team' => $query->join('teams', 'teams.id', '=', 'leads.team_id')
                ->selectRaw('leads.team_id as dimension_id, teams.name as dimension_name')
                ->whereNotNull('leads.team_id')
                ->groupBy('leads.team_id', 'teams.name'),

            'agent' => $query->join('users', 'users.id', '=', 'leads.assigned_to')
                ->selectRaw('leads.assigned_to as dimension_id, users.name as dimension_name')
                ->whereNotNull('leads.assigned_to')
                ->groupBy('leads.assigned_to', 'users.name'),

            'insurance_type' => $query->selectRaw('leads.insurance_type as dimension_id')
                ->whereNotNull('leads.insurance_type')
                ->groupBy('leads.insurance_type'),

            default => $query->join('lead_sources', 'lead_sources.id', '=', 'leads.lead_source_id')
                ->selectRaw('leads.lead_source_id as dimension_id, lead_sources.name as dimension_name')
                ->whereNotNull('leads.lead_source_id')
                ->groupBy('leads.lead_source_id', 'lead_sources.name'),
        };

        $query->selectRaw('count(*) as total')
            ->selectRaw('sum(case when leads.status = ? then 1 else 0 end) as validated', [LeadStatusEnum::VALIDE->value]);

        $this->applyDateRange($query, 'leads.created_at', $from, $to);

        if ($teamId) {
            $query->where('leads.team_id', $teamId);
        }

        if ($scope) {
            $scope($query);
        }

        return $query->orderByDesc('total')->paginate($perPage);
    }

    public function paginateRevenueReport(?Closure $scope, ?string $paymentStatus, ?int $teamId, ?int $agentId, ?string $from, ?string $to, int $perPage): LengthAwarePaginator
    {
        $query = $this->revenueBaseQuery($scope, $paymentStatus, $teamId, $agentId, $from, $to)
            ->with(['assignedAgent:id,name', 'team:id,name'])
            ->withSum(['payments' => fn (Builder $query) => $query->where('payments.status', PaymentRecordStatusEnum::REUSSI->value)], 'amount')
            ->withCount('payments')
            ->orderByDesc('validated_at');

        return $query->paginate($perPage);
    }

    public function revenueSummary(?Closure $scope, ?string $paymentStatus, ?int $teamId, ?int $agentId, ?string $from, ?string $to): array
    {
        $query = $this->revenueBaseQuery($scope, $paymentStatus, $teamId, $agentId, $from, $to);

        $totalExpected = (clone $query)->sum('expected_revenue');
        $leadsCount = (clone $query)->count();

        $byStatus = (clone $query)
            ->selectRaw('payment_status, count(*) as aggregate')
            ->groupBy('payment_status')
            ->pluck('aggregate', 'payment_status');

        $leadIds = (clone $query)->select('id');
        $totalReceived = \App\Models\Payment::received()->whereIn('lead_id', $leadIds)->sum('amount');

        return [
            'total_expected' => round((float) $totalExpected, 2),
            'total_received' => round((float) $totalReceived, 2),
            'total_remaining' => round((float) bcsub((string) $totalExpected, (string) $totalReceived, 2), 2),
            'leads_count' => $leadsCount,
            'fully_paid' => (int) ($byStatus[PaymentStatusEnum::PAYE->value] ?? 0),
            'partially_paid' => (int) ($byStatus[PaymentStatusEnum::PARTIELLEMENT_PAYE->value] ?? 0),
            'unpaid' => (int) ($byStatus[PaymentStatusEnum::NON_PAYE->value] ?? 0),
        ];
    }

    public function leadSummary(?Closure $scope, LeadFilter $filters): array
    {
        $query = Lead::query();
        if ($scope) {
            $scope($query);
        }
        $query->filter($filters);

        $byStatus = (clone $query)->reorder()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($n) => (int) $n);

        $byStage = [];
        foreach (LeadStatusEnum::cases() as $status) {
            $byStage[$status->stage()] = ($byStage[$status->stage()] ?? 0) + ($byStatus[$status->value] ?? 0);
        }

        $total = (int) $byStatus->sum();
        $validated = (int) ($byStatus[LeadStatusEnum::VALIDE->value] ?? 0);

        return [
            'total' => $total,
            'validated' => $validated,
            'conversion_rate' => $total > 0 ? round($validated / $total * 100, 2) : 0.0,
            'unassigned' => (clone $query)->reorder()->whereNull('assigned_to')->count(),
            'by_status' => $byStatus,
            'by_stage' => $byStage,
        ];
    }

    public function appointmentSummary(?Closure $scope, AppointmentFilter $filters): array
    {
        $query = Appointment::query();
        if ($scope) {
            $scope($query);
        }
        $query->filter($filters);

        $byStatus = (clone $query)->reorder()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($n) => (int) $n);

        $total = (int) $byStatus->sum();
        $done = (int) ($byStatus[AppointmentStatusEnum::REALISE->value] ?? 0);

        return [
            'total' => $total,
            'planned' => (int) ($byStatus[AppointmentStatusEnum::PLANIFIE->value] ?? 0) + (int) ($byStatus[AppointmentStatusEnum::CONFIRME->value] ?? 0),
            'completed' => $done,
            'cancelled' => (int) ($byStatus[AppointmentStatusEnum::ANNULE->value] ?? 0),
            'no_show' => (int) ($byStatus[AppointmentStatusEnum::NON_VENU->value] ?? 0),
            'rescheduled' => (int) ($byStatus[AppointmentStatusEnum::REPORTE->value] ?? 0),
            'completion_rate' => $total > 0 ? round($done / $total * 100, 2) : 0.0,
            'overdue' => (clone $query)->reorder()
                ->whereIn('status', AppointmentStatusEnum::openValues())
                ->where('scheduled_at', '<', now())
                ->count(),
            'by_status' => $byStatus,
        ];
    }

    protected function revenueBaseQuery(?Closure $scope, ?string $paymentStatus, ?int $teamId, ?int $agentId, ?string $from, ?string $to): Builder
    {
        $query = Lead::query()->where('status', LeadStatusEnum::VALIDE->value);

        if ($scope) {
            $scope($query);
        }

        if ($paymentStatus) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        if ($agentId) {
            $query->where('assigned_to', $agentId);
        }

        $this->applyDateRange($query, 'validated_at', $from, $to);

        return $query;
    }

    protected function applyDateRange(Builder $query, string $column, ?string $from, ?string $to): void
    {
        if ($from) {
            $query->where($column, '>=', $from);
        }

        if ($to) {
            $query->where($column, '<=', $to);
        }
    }
}
