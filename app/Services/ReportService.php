<?php

namespace App\Services;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Exceptions\ApiException;
use App\Filters\AppointmentFilter;
use App\Filters\LeadFilter;
use App\Models\User;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use App\Repositories\Contracts\LeadRepositoryInterface;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Support\LeadSourceScope;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
            || $this->seesTeamReports($user);
    }

    /**
     * Reports limited to the user's own team: team-report permission, or a
     * supervisor (agent with the team-stats permission).
     */
    protected function seesTeamReports(User $user): bool
    {
        return $user->can(PermissionEnum::REPORTS_VIEW_TEAM->value)
            || $user->can(PermissionEnum::DASHBOARD_VIEW_TEAM_STATS->value);
    }

    /**
     * A supervisor sees the reports of his team through the team-stats
     * permission only: no money figures (revenue stays personal).
     */
    public function hidesRevenue(User $user): bool
    {
        return ! $user->can(PermissionEnum::REPORTS_VIEW_ALL->value)
            && ! $user->can(PermissionEnum::REPORTS_VIEW_TEAM->value)
            && $user->can(PermissionEnum::DASHBOARD_VIEW_TEAM_STATS->value);
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

    /**
     * « CA par agent »: one agent's money over the period (default: the
     * last 12 months), and the agents the user may pick from:
     *   - REVENUE_VIEW_ALL: every agent;
     *   - REVENUE_VIEW_TEAM: the agents of his team;
     *   - REVENUE_VIEW_PERSONAL: himself only, without the team ranking.
     *
     * @return array<string, mixed>
     *
     * @throws ApiException
     */
    public function agentRevenue(User $user, ?int $agentId, ?string $from, ?string $to): array
    {
        $to = $to ? CarbonImmutable::parse($to) : CarbonImmutable::today();
        $from = $from ? CarbonImmutable::parse($from) : $to->startOfMonth()->subMonths(11);

        $agents = $this->revenueAgents($user);
        $agentId ??= $agents->contains('id', $user->id) ? $user->id : $agents->first()?->id;
        $agent = $agents->firstWhere('id', $agentId);

        if ($agent === null) {
            throw new ApiException('Vous ne pouvez pas consulter le chiffre d’affaires de cet agent.', 403);
        }

        $teamAgents = $this->seesOthersRevenue($user) && $agent->team_id
            ? $agents->where('team_id', $agent->team_id)->pluck('id')->all()
            : null;

        return [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'is_active' => (bool) $agent->is_active,
                'team' => $agent->team ? ['id' => $agent->team->id, 'name' => $agent->team->name] : null,
            ],
            'agents' => $agents->map(fn (User $a) => ['id' => $a->id, 'name' => $a->name, 'is_active' => (bool) $a->is_active])->values()->all(),
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ] + $this->reports->agentRevenue($agent, $from, $to, $user->allowedSourceIds(), $teamAgents);
    }

    protected function seesOthersRevenue(User $user): bool
    {
        return $user->can(PermissionEnum::REVENUE_VIEW_ALL->value)
            || $user->can(PermissionEnum::REVENUE_VIEW_TEAM->value);
    }

    /**
     * The active agents whose revenue the user may see (disabled ones are hidden).
     *
     * @return Collection<int, User>
     */
    protected function revenueAgents(User $user): Collection
    {
        $query = User::query()->role(RoleEnum::AGENT->value)->with('team:id,name')
            ->where('is_active', true)->orderBy('name');

        if ($user->can(PermissionEnum::REVENUE_VIEW_ALL->value)) {
            return $query->get();
        }

        if ($user->can(PermissionEnum::REVENUE_VIEW_TEAM->value)) {
            return $user->team_id ? $query->where('team_id', $user->team_id)->get() : collect();
        }

        if ($user->can(PermissionEnum::REVENUE_VIEW_PERSONAL->value)) {
            return User::query()->whereKey($user->id)->with('team:id,name')->get();
        }

        return collect();
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
     *   - REPORTS_VIEW_TEAM or supervisor: restrict to the user's own team
     *   - neither: restrict to nothing
     */
    protected function scope(User $user, string $teamColumn): Closure
    {
        return function (Builder $query) use ($user, $teamColumn) {
            if ($user->can(PermissionEnum::REPORTS_VIEW_ALL->value)) {
                return;
            }

            if ($this->seesTeamReports($user)) {
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

            if ($this->seesTeamReports($user)) {
                $query->whereHas('agent', fn (Builder $q) => $q->where('team_id', $user->team_id));

                return;
            }

            $query->whereRaw('1 = 0');
        };
    }
}
