<?php

namespace App\Repositories\Eloquent;

use App\Enums\AppointmentStatusEnum;
use App\Enums\LeadStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\RoleEnum;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use App\Repositories\Contracts\ReportRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ReportRepository implements ReportRepositoryInterface
{
    public function paginateTeamReport(?Closure $scope, ?string $from, ?string $to, int $perPage): LengthAwarePaginator
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
            }]);

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
            }]);

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        if ($scope) {
            $scope($query);
        }

        return $query->paginate($perPage);
    }

    public function paginateConversionReport(string $groupBy, ?Closure $scope, ?string $from, ?string $to, int $perPage): LengthAwarePaginator
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

        if ($scope) {
            $scope($query);
        }

        return $query->orderByDesc('total')->paginate($perPage);
    }

    public function paginateRevenueReport(?Closure $scope, ?string $paymentStatus, ?int $teamId, ?int $agentId, ?string $from, ?string $to, int $perPage): LengthAwarePaginator
    {
        $query = $this->revenueBaseQuery($scope, $paymentStatus, $teamId, $agentId, $from, $to)
            ->with(['assignedAgent:id,name', 'team:id,name'])
            ->withSum('payments', 'amount')
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
        $totalReceived = \App\Models\Payment::whereIn('lead_id', $leadIds)->sum('amount');

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
