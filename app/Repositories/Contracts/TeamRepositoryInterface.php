<?php

namespace App\Repositories\Contracts;

use App\Filters\TeamFilter;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TeamRepositoryInterface extends RepositoryInterface
{
    /**
     * Paginate teams, applying the given filters and eager-loading the
     * manager and a count of members.
     */
    public function paginateFiltered(TeamFilter $filters, int $perPage = 15, ?Closure $scope = null): LengthAwarePaginator;

    /**
     * Paginate the users belonging to a team.
     */
    public function paginateMembers(int $teamId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Count the number of users currently assigned to a team.
     */
    public function membersCount(int $teamId): int;

    /**
     * Aggregate statistics for a team: members, leads and appointments.
     *
     * @return array<string, mixed>
     */
    public function statistics(int $teamId): array;
}
