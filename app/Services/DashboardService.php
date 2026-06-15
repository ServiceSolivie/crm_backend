<?php

namespace App\Services;

use App\Enums\PermissionEnum;
use App\Models\User;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class DashboardService
{
    public function __construct(protected DashboardRepositoryInterface $dashboard) {}

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
    public function kpis(User $user, ?string $from = null, ?string $to = null): array
    {
        [$leadScope, $appointmentScope] = $this->scopes($user);

        return $this->dashboard->kpis($leadScope, $appointmentScope, $from, $to);
    }

    /**
     * @return array<string, mixed>
     */
    public function statistics(User $user, ?string $from = null, ?string $to = null): array
    {
        [$leadScope, $appointmentScope] = $this->scopes($user);

        return [
            'leads' => $this->dashboard->leadStatistics($leadScope, $from, $to),
            'appointments' => $this->dashboard->appointmentStatistics($appointmentScope, $from, $to),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function aggregations(User $user, ?string $from = null, ?string $to = null): array
    {
        [$leadScope, , $level] = $this->scopes($user);

        return $this->dashboard->aggregations($leadScope, $level, $from, $to);
    }

    /**
     * @return array<string, mixed>
     */
    public function charts(User $user, int $days = 14): array
    {
        [$leadScope, $appointmentScope] = $this->scopes($user);

        return $this->dashboard->charts($leadScope, $appointmentScope, $days);
    }

    /**
     * Resolve the lead/appointment query scopes and visibility level for
     * the given user, based on their dashboard permissions.
     *
     * @return array{0: ?\Closure, 1: ?\Closure, 2: string}
     */
    protected function scopes(User $user): array
    {
        if ($user->can(PermissionEnum::DASHBOARD_VIEW_GLOBAL->value)) {
            return [null, null, 'GLOBAL'];
        }

        if ($user->can(PermissionEnum::DASHBOARD_VIEW_TEAM->value)) {
            return [
                fn (Builder $query) => $query->where('team_id', $user->team_id),
                fn (Builder $query) => $query->whereHas('agent', fn (Builder $q) => $q->where('team_id', $user->team_id)),
                'TEAM',
            ];
        }

        if ($user->can(PermissionEnum::DASHBOARD_VIEW_PERSONAL->value)) {
            return [
                fn (Builder $query) => $query->where('assigned_to', $user->id),
                fn (Builder $query) => $query->where('agent_id', $user->id),
                'PERSONAL',
            ];
        }

        return [
            fn (Builder $query) => $query->whereRaw('1 = 0'),
            fn (Builder $query) => $query->whereRaw('1 = 0'),
            'NONE',
        ];
    }
}
