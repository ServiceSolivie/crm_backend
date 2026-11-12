<?php

namespace App\Services;

use App\Enums\PermissionEnum;
use App\Filters\AppointmentFilter;
use App\Filters\LeadFilter;
use App\Models\User;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Support\LeadSourceScope;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ReportService
{
    public function __construct(
        protected LeadRepositoryInterface $leads,
        protected AppointmentRepositoryInterface $appointments,
        protected ReportRepositoryInterface $reports,
    ) {}

    /**
     * Whether the given user may view any reports at all.
     */
    public function canView(User $user): bool
    {
        return $user->can(PermissionEnum::REPORTS_VIEW_ALL->value)
            || $user->can(PermissionEnum::REPORTS_VIEW_TEAM->value);
    }

    public function leadReport(User $user, LeadFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->leads->paginateFiltered($filters, $perPage, $this->leadScope($user, 'team_id'));
    }

    public function appointmentReport(User $user, AppointmentFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->appointments->paginateFiltered($filters, $perPage, $this->appointmentScope($user));
    }

    public function leadSummary(User $user, LeadFilter $filters): array
    {
        return $this->reports->leadSummary($this->leadScope($user, 'team_id'), $filters);
    }

    public function appointmentSummary(User $user, AppointmentFilter $filters): array
    {
        return $this->reports->appointmentSummary($this->appointmentScope($user), $filters);
    }

    public function teamReport(User $user, ?int $teamId, ?string $from, ?string $to, int $perPage = 15): LengthAwarePaginator
    {
        return $this->reports->paginateTeamReport($this->scope($user, 'id'), $teamId, $from, $to, $perPage, $user->allowedSourceIds());
    }

    public function agentReport(User $user, ?int $teamId, ?string $from, ?string $to, int $perPage = 15): LengthAwarePaginator
    {
        return $this->reports->paginateAgentReport($this->scope($user, 'team_id'), $teamId, $from, $to, $perPage, $user->allowedSourceIds());
    }

    public function conversionReport(User $user, string $groupBy, ?int $teamId, ?string $from, ?string $to, int $perPage = 15): LengthAwarePaginator
    {
        return $this->reports->paginateConversionReport($groupBy, $this->leadScope($user, 'leads.team_id', 'leads.lead_source_id'), $teamId, $from, $to, $perPage);
    }

    public function canViewRevenue(User $user): bool
    {
        return $user->can(PermissionEnum::REVENUE_VIEW_ALL->value)
            || $user->can(PermissionEnum::REVENUE_VIEW_TEAM->value)
            || $user->can(PermissionEnum::REVENUE_VIEW_PERSONAL->value);
    }

    public function revenueReport(User $user, ?string $paymentStatus, ?int $teamId, ?int $agentId, ?string $from, ?string $to, int $perPage = 15): LengthAwarePaginator
    {
        return $this->reports->paginateRevenueReport(
            $this->revenueScope($user),
            $paymentStatus,
            $teamId,
            $agentId,
            $from,
            $to,
            $perPage,
        );
    }

    public function revenueSummary(User $user, ?string $paymentStatus, ?int $teamId, ?int $agentId, ?string $from, ?string $to): array
    {
        return $this->reports->revenueSummary(
            $this->revenueScope($user),
            $paymentStatus,
            $teamId,
            $agentId,
            $from,
            $to,
        );
    }

    protected function revenueScope(User $user): ?Closure
    {
        $sourceIds = $user->allowedSourceIds();
        $base = $this->baseRevenueScope($user);

        if ($sourceIds === null) {
            return $base;
        }

        return function (Builder $query) use ($base, $sourceIds) {
            if ($base) {
                $base($query);
            }

            LeadSourceScope::leads($query, $sourceIds);
        };
    }

    protected function baseRevenueScope(User $user): ?Closure
    {
        if ($user->can(PermissionEnum::REVENUE_VIEW_ALL->value)) {
            return null;
        }

        if ($user->can(PermissionEnum::REVENUE_VIEW_TEAM->value)) {
            return fn (Builder $query) => $query->where('team_id', $user->team_id);
        }

        if ($user->can(PermissionEnum::REVENUE_VIEW_PERSONAL->value)) {
            return fn (Builder $query) => $query->where('assigned_to', $user->id);
        }

        return fn (Builder $query) => $query->whereRaw('1 = 0');
    }

    /**
     * Restrict a query by the given team-identifying column according to
     * the user's reporting permissions:
     *
     *   - REPORTS_VIEW_ALL: no restriction
     *   - REPORTS_VIEW_TEAM: restrict to the user's own team
     *   - neither: restrict to nothing
     */
    protected function scope(User $user, string $teamColumn): Closure
    {
        return function (Builder $query) use ($user, $teamColumn) {
            if ($user->can(PermissionEnum::REPORTS_VIEW_ALL->value)) {
                return;
            }

            if ($user->can(PermissionEnum::REPORTS_VIEW_TEAM->value)) {
                $query->where($teamColumn, $user->team_id);

                return;
            }

            $query->whereRaw('1 = 0');
        };
    }

    /**
     * Team scope on a leads query, limited to the user's lead sources (marketing).
     */
    protected function leadScope(User $user, string $teamColumn, string $sourceColumn = 'lead_source_id'): Closure
    {
        $scope = $this->scope($user, $teamColumn);
        $sourceIds = $user->allowedSourceIds();

        return function (Builder $query) use ($scope, $sourceIds, $sourceColumn) {
            $scope($query);
            LeadSourceScope::leads($query, $sourceIds, $sourceColumn);
        };
    }

    /**
     * Team scope on an appointments query, limited to the user's lead sources.
     */
    protected function appointmentScope(User $user): Closure
    {
        $scope = $this->agentTeamScope($user);
        $sourceIds = $user->allowedSourceIds();

        return function (Builder $query) use ($scope, $sourceIds) {
            $scope($query);
            LeadSourceScope::viaLead($query, $sourceIds);
        };
    }

    /**
     * Restrict an appointment query to the user's team via the agent relation.
     */
    protected function agentTeamScope(User $user): Closure
    {
        return function (Builder $query) use ($user) {
            if ($user->can(PermissionEnum::REPORTS_VIEW_ALL->value)) {
                return;
            }

            if ($user->can(PermissionEnum::REPORTS_VIEW_TEAM->value)) {
                $query->whereHas('agent', fn (Builder $q) => $q->where('team_id', $user->team_id));

                return;
            }

            $query->whereRaw('1 = 0');
        };
    }
}
