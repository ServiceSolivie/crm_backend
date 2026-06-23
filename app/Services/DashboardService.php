<?php

namespace App\Services;

use App\Enums\PermissionEnum;
use App\Models\User;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    public function __construct(
        protected DashboardRepositoryInterface $dashboard,
        protected AppointmentService $appointments,
    ) {}

    /**
     * Whether the given user may view any dashboard data at all.
     */
    public function canView(User $user): bool
    {
        return $user->can(PermissionEnum::DASHBOARD_VIEW_GLOBAL->value)
            || $user->can(PermissionEnum::DASHBOARD_VIEW_TEAM->value)
            || $user->can(PermissionEnum::DASHBOARD_VIEW_PERSONAL->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function kpis(User $user, ?string $from = null, ?string $to = null, ?int $teamId = null, ?int $agentId = null): array
    {
        [$leadScope, $appointmentScope] = $this->scopes($user, $teamId, $agentId);

        return $this->dashboard->kpis($leadScope, $appointmentScope, $from, $to);
    }

    /**
     * @return array<string, mixed>
     */
    public function statistics(User $user, ?string $from = null, ?string $to = null, ?int $teamId = null, ?int $agentId = null): array
    {
        [$leadScope, $appointmentScope] = $this->scopes($user, $teamId, $agentId);

        return [
            'leads' => $this->dashboard->leadStatistics($leadScope, $from, $to),
            'appointments' => $this->dashboard->appointmentStatistics($appointmentScope, $from, $to),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function aggregations(User $user, ?string $from = null, ?string $to = null, ?int $teamId = null, ?int $agentId = null): array
    {
        [$leadScope, , $level] = $this->scopes($user, $teamId, $agentId);

        return $this->dashboard->aggregations($leadScope, $level, $from, $to);
    }

    /**
     * @return array<string, mixed>
     */
    public function charts(User $user, int $days = 14, ?int $teamId = null, ?int $agentId = null): array
    {
        [$leadScope, $appointmentScope] = $this->scopes($user, $teamId, $agentId);

        return $this->dashboard->charts($leadScope, $appointmentScope, $days);
    }

    /**
     * @return array<string, mixed>
     */
    public function revenue(User $user, ?string $from = null, ?string $to = null, ?int $teamId = null, ?int $agentId = null): array
    {
        $scope = $this->revenueScope($user, $teamId, $agentId);

        return $this->dashboard->revenue($scope, $from, $to);
    }

    /**
     * Whether the given user may view revenue data.
     */
    public function canViewRevenue(User $user): bool
    {
        return $user->can(PermissionEnum::REVENUE_VIEW_ALL->value)
            || $user->can(PermissionEnum::REVENUE_VIEW_TEAM->value)
            || $user->can(PermissionEnum::REVENUE_VIEW_PERSONAL->value);
    }

    protected function revenueScope(User $user, ?int $teamId = null, ?int $agentId = null): ?Closure
    {
        if ($user->can(PermissionEnum::REVENUE_VIEW_ALL->value)) {
            return $this->withLeadFilters(null, $teamId, $agentId);
        }

        if ($user->can(PermissionEnum::REVENUE_VIEW_TEAM->value)) {
            return $this->withLeadFilters(
                fn (Builder $query) => $query->where('team_id', $user->team_id),
                $teamId,
                $agentId,
            );
        }

        if ($user->can(PermissionEnum::REVENUE_VIEW_PERSONAL->value)) {
            return $this->withLeadFilters(
                fn (Builder $query) => $query->where('assigned_to', $user->id),
                $teamId,
                $agentId,
            );
        }

        return fn (Builder $query) => $query->whereRaw('1 = 0');
    }

    /**
     * Resolve the lead/appointment query scopes and visibility level for
     * the given user. Lead scoping follows the user's dashboard permissions;
     * appointment scoping always follows AppointmentService::visibilityScope()
     * so dashboard widgets stay consistent with the appointments module.
     *
     * An optional team/agent filter narrows the permission-derived scope
     * further. Because the filter is always AND-combined with the base
     * scope, it can only narrow results — it can never let a user see data
     * outside their permission boundary.
     *
     * @return array{0: ?\Closure, 1: ?\Closure, 2: string}
     */
    protected function scopes(User $user, ?int $teamId = null, ?int $agentId = null): array
    {
        $appointmentScope = $this->appointments->visibilityScope($user);

        if ($user->can(PermissionEnum::DASHBOARD_VIEW_GLOBAL->value)) {
            return [
                $this->withLeadFilters(null, $teamId, $agentId),
                $this->withAppointmentFilters($appointmentScope, $teamId, $agentId),
                'GLOBAL',
            ];
        }

        if ($user->can(PermissionEnum::DASHBOARD_VIEW_TEAM->value)) {
            return [
                $this->withLeadFilters(
                    fn (Builder $query) => $query->where('team_id', $user->team_id),
                    $teamId,
                    $agentId,
                ),
                $this->withAppointmentFilters($appointmentScope, $teamId, $agentId),
                'TEAM',
            ];
        }

        if ($user->can(PermissionEnum::DASHBOARD_VIEW_PERSONAL->value)) {
            return [
                $this->withLeadFilters(
                    fn (Builder $query) => $query->where('assigned_to', $user->id),
                    $teamId,
                    $agentId,
                ),
                $this->withAppointmentFilters($appointmentScope, $teamId, $agentId),
                'PERSONAL',
            ];
        }

        return [
            fn (Builder $query) => $query->whereRaw('1 = 0'),
            $appointmentScope,
            'NONE',
        ];
    }

    /**
     * Wrap a lead query scope with additional team/agent constraints.
     * Always AND-combined with the base scope, so it can only narrow results.
     */
    protected function withLeadFilters(?Closure $baseScope, ?int $teamId, ?int $agentId): ?Closure
    {
        if (! $teamId && ! $agentId) {
            return $baseScope;
        }

        return function (Builder $query) use ($baseScope, $teamId, $agentId) {
            if ($baseScope) {
                $baseScope($query);
            }

            if ($teamId) {
                $query->where('team_id', $teamId);
            }

            if ($agentId) {
                $query->where('assigned_to', $agentId);
            }
        };
    }

    /**
     * Wrap an appointment query scope with additional team/agent constraints.
     * Appointments have no team_id column, so team filtering traverses the
     * related lead; agent filtering uses the appointment's own agent_id.
     */
    protected function withAppointmentFilters(?Closure $baseScope, ?int $teamId, ?int $agentId): ?Closure
    {
        if (! $teamId && ! $agentId) {
            return $baseScope;
        }

        return function (Builder $query) use ($baseScope, $teamId, $agentId) {
            if ($baseScope) {
                $baseScope($query);
            }

            if ($teamId) {
                $query->whereHas('lead', fn (Builder $leads) => $leads->where('team_id', $teamId));
            }

            if ($agentId) {
                $query->where('agent_id', $agentId);
            }
        };
    }
}
