<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Exceptions\ApiException;
use App\Filters\TeamFilter;
use App\Models\Team;
use App\Models\User;
use App\Repositories\Contracts\TeamRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TeamService extends BaseService
{
    public function __construct(protected TeamRepositoryInterface $teams)
    {
        parent::__construct($teams);
    }

    /**
     * Paginate teams, scoped to what the given user is allowed to see.
     */
    public function paginateForUser(User $user, TeamFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->teams->paginateFiltered($filters, $perPage, $this->visibilityScope($user));
    }

    /**
     * Super admins see every team. Managers and agents only see the
     * team they manage or belong to.
     */
    protected function visibilityScope(User $user): \Closure
    {
        return function (Builder $query) use ($user) {
            if ($user->hasRole(RoleEnum::SUPER_ADMIN->value)) {
                return;
            }

            $query->where(function (Builder $query) use ($user) {
                $query->where('manager_id', $user->id)
                    ->orWhere('leader_id', $user->id);

                if ($user->team_id !== null) {
                    $query->orWhere('id', $user->team_id);
                }
            });
        };
    }

    public function createTeam(array $data): Team
    {
        /** @var Team $team */
        $team = $this->teams->create($data);

        return $team;
    }

    public function updateTeam(Team $team, array $data): Team
    {
        /** @var Team $updated */
        $updated = $this->teams->update($team->id, $data);

        return $updated;
    }

    /**
     * Delete a team, refusing to do so while it still has members assigned.
     */
    public function deleteTeam(Team $team): bool
    {
        if ($this->teams->membersCount($team->id) > 0) {
            throw new ApiException('Cannot delete a team that still has members assigned to it.', 409);
        }

        return $this->teams->delete($team->id);
    }

    public function members(Team $team, int $perPage = 15): LengthAwarePaginator
    {
        return $this->teams->paginateMembers($team->id, $perPage);
    }

    /**
     * Assign a user to a team, moving them out of any previous team.
     */
    public function addMember(Team $team, User $user): User
    {
        $user->update(['team_id' => $team->id]);

        return $user->refresh();
    }

    /**
     * Remove a user from a team.
     */
    public function removeMember(Team $team, User $user): User
    {
        if ($user->team_id !== $team->id) {
            throw new ApiException('This user does not belong to the given team.', 422);
        }

        $user->update(['team_id' => null]);

        return $user->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function statistics(Team $team): array
    {
        return $this->teams->statistics($team->id);
    }
}
