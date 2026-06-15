<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    /**
     * Determine whether the user can view the leads list at all.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::LEADS_VIEW_ALL->value)
            || $user->can(PermissionEnum::LEADS_VIEW_TEAM->value)
            || $user->can(PermissionEnum::LEADS_VIEW_ASSIGNED->value);
    }

    public function view(User $user, Lead $lead): bool
    {
        return $this->canAccess($user, $lead);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::LEADS_CREATE->value);
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::LEADS_UPDATE->value) && $this->canAccess($user, $lead);
    }

    public function delete(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::LEADS_DELETE->value) && $this->canAccess($user, $lead);
    }

    public function assign(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::LEADS_ASSIGN->value) && $this->canAccess($user, $lead);
    }

    public function updateStatus(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::LEADS_UPDATE_STATUS->value) && $this->canAccess($user, $lead);
    }

    public function manageNotes(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::LEAD_NOTES_MANAGE->value) && $this->canAccess($user, $lead);
    }

    public function viewHistory(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::LEAD_STATUS_HISTORY_VIEW->value) && $this->canAccess($user, $lead);
    }

    /**
     * A user may access a lead if they can see all leads, see their team's
     * leads and the lead belongs to their team, or see assigned leads and
     * the lead is assigned to them.
     */
    protected function canAccess(User $user, Lead $lead): bool
    {
        if ($user->can(PermissionEnum::LEADS_VIEW_ALL->value)) {
            return true;
        }

        if ($user->can(PermissionEnum::LEADS_VIEW_TEAM->value) && $lead->team_id !== null && $lead->team_id === $user->team_id) {
            return true;
        }

        if ($user->can(PermissionEnum::LEADS_VIEW_ASSIGNED->value) && $lead->assigned_to === $user->id) {
            return true;
        }

        return false;
    }
}
