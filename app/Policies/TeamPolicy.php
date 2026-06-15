<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::TEAMS_VIEW->value);
    }

    public function view(User $user, Team $team): bool
    {
        return $user->can(PermissionEnum::TEAMS_VIEW->value) && $this->canAccess($user, $team);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::TEAMS_CREATE->value);
    }

    public function update(User $user, Team $team): bool
    {
        return $user->can(PermissionEnum::TEAMS_UPDATE->value) && $this->canAccess($user, $team);
    }

    public function delete(User $user, Team $team): bool
    {
        return $user->can(PermissionEnum::TEAMS_DELETE->value) && $this->canAccess($user, $team);
    }

    public function manageMembers(User $user, Team $team): bool
    {
        return $user->can(PermissionEnum::TEAMS_MANAGE_MEMBERS->value) && $this->canAccess($user, $team);
    }

    public function viewStatistics(User $user, Team $team): bool
    {
        return $user->can(PermissionEnum::TEAMS_VIEW->value) && $this->canAccess($user, $team);
    }

    /**
     * Super admins may access any team. Managers and agents may only
     * access a team they manage or belong to.
     */
    protected function canAccess(User $user, Team $team): bool
    {
        if ($user->hasRole(RoleEnum::SUPER_ADMIN->value)) {
            return true;
        }

        if ($team->manager_id === $user->id) {
            return true;
        }

        if ($user->team_id !== null && $user->team_id === $team->id) {
            return true;
        }

        return false;
    }
}
