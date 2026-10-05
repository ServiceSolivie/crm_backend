<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Call;
use App\Models\User;

/**
 * Agents only see (and listen to) their own calls; team leaders and
 * managers see their team's calls; super admins see everything.
 */
class CallPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::CALLS_VIEW_ALL->value)
            || $user->can(PermissionEnum::CALLS_VIEW_TEAM->value)
            || $user->can(PermissionEnum::CALLS_VIEW_OWN->value);
    }

    public function view(User $user, Call $call): bool
    {
        return $this->allowed($user, $call, PermissionEnum::CALLS_VIEW_ALL, PermissionEnum::CALLS_VIEW_TEAM, PermissionEnum::CALLS_VIEW_OWN);
    }

    /**
     * Listen to the recording / voicemail and read the AI summary and transcript.
     */
    public function listen(User $user, Call $call): bool
    {
        return $this->allowed($user, $call, PermissionEnum::CALLS_LISTEN_ALL, PermissionEnum::CALLS_LISTEN_TEAM, PermissionEnum::CALLS_LISTEN_OWN);
    }

    protected function allowed(User $user, Call $call, PermissionEnum $all, PermissionEnum $team, PermissionEnum $own): bool
    {
        if ($user->can($all->value)) {
            return true;
        }

        if ($user->can($team->value) && $user->team_id !== null && $this->belongsToTeam($call, $user->team_id)) {
            return true;
        }

        return $user->can($own->value) && $call->user_id === $user->id;
    }

    protected function belongsToTeam(Call $call, int $teamId): bool
    {
        return $call->team_id === $teamId || $call->lead?->team_id === $teamId;
    }
}
