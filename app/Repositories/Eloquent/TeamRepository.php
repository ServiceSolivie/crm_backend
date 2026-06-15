<?php

namespace App\Repositories\Eloquent;

use App\Enums\AppointmentStatusEnum;
use App\Enums\LeadStatusEnum;
use App\Filters\TeamFilter;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\Team;
use App\Models\User;
use App\Repositories\Contracts\TeamRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TeamRepository extends BaseRepository implements TeamRepositoryInterface
{
    public function model(): string
    {
        return Team::class;
    }

    public function paginateFiltered(TeamFilter $filters, int $perPage = 15, ?Closure $scope = null): LengthAwarePaginator
    {
        $query = $this->newQuery()
            ->with('manager')
            ->withCount('members');

        if ($scope) {
            $scope($query);
        }

        return $query->filter($filters)->paginate($perPage);
    }

    public function paginateMembers(int $teamId, int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->where('team_id', $teamId)
            ->paginate($perPage);
    }

    public function membersCount(int $teamId): int
    {
        return User::query()->where('team_id', $teamId)->count();
    }

    public function statistics(int $teamId): array
    {
        $leadsByStatus = Lead::query()
            ->where('team_id', $teamId)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $totalLeads = (int) $leadsByStatus->sum();

        $appointmentsByStatus = Appointment::query()
            ->whereHas('agent', fn ($query) => $query->where('team_id', $teamId))
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $totalAppointments = (int) $appointmentsByStatus->sum();

        $validatedLeads = (int) ($leadsByStatus[LeadStatusEnum::VALIDE->value] ?? 0);

        return [
            'members_count' => $this->membersCount($teamId),
            'leads' => [
                'total' => $totalLeads,
                'by_status' => $this->fillStatusCounts(LeadStatusEnum::values(), $leadsByStatus),
                'conversion_rate' => $totalLeads > 0 ? round(($validatedLeads / $totalLeads) * 100, 2) : 0.0,
            ],
            'appointments' => [
                'total' => $totalAppointments,
                'by_status' => $this->fillStatusCounts(AppointmentStatusEnum::values(), $appointmentsByStatus),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $statuses
     * @param  Collection<string, int>  $counts
     * @return array<string, int>
     */
    protected function fillStatusCounts(array $statuses, Collection $counts): array
    {
        $result = [];

        foreach ($statuses as $status) {
            $result[$status] = (int) ($counts[$status] ?? 0);
        }

        return $result;
    }
}
