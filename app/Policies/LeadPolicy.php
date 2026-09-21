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
            || $user->can(PermissionEnum::LEADS_VIEW_ASSIGNED->value)
            || $user->can(PermissionEnum::LEADS_VIEW_GESTION_ASSIGNED->value);
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

    /**
     * Creating a second lead for a different product, for a contact you
     * already have, doesn't require the general LEADS_CREATE permission -
     * only that you can already access this lead (own it, or see your
     * team's/all leads).
     */
    public function crossSell(User $user, Lead $lead): bool
    {
        return $this->canAccess($user, $lead);
    }

    public function updateStatus(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::LEADS_UPDATE_STATUS->value) && $this->canAccess($user, $lead);
    }

    public function manageNotes(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::LEAD_NOTES_MANAGE->value) && $this->canAccess($user, $lead);
    }

    public function manageCalls(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::LEAD_CALLS_MANAGE->value) && $this->canAccess($user, $lead);
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

        if ($user->can(PermissionEnum::LEADS_VIEW_GESTION_ASSIGNED->value) && $lead->gestion_assigned_to === $user->id) {
            return true;
        }

        return false;
    }
}
