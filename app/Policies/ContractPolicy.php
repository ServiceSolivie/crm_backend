<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\User;

class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::CONTRACTS_VIEW->value);
    }

    public function view(User $user, Contract $contract): bool
    {
        return $user->can(PermissionEnum::CONTRACTS_VIEW->value) && $this->canAccess($user, $contract);
    }

    /**
     * Generate a contract, optionally linked to a lead the user must be
     * able to access.
     */
    public function generate(User $user, ?Lead $lead = null): bool
    {
        if (! $user->can(PermissionEnum::CONTRACTS_GENERATE->value)) {
            return false;
        }

        return $lead === null || $this->canAccessLead($user, $lead);
    }

    public function download(User $user, Contract $contract): bool
    {
        return $this->view($user, $contract);
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $user->can(PermissionEnum::CONTRACTS_DELETE->value) && $this->canAccess($user, $contract);
    }

    /**
     * A contract is accessible if the user generated it, can see all
     * leads, or can access its linked lead through team/assignment scope.
     */
    protected function canAccess(User $user, Contract $contract): bool
    {
        if ($user->can(PermissionEnum::LEADS_VIEW_ALL->value)) {
            return true;
        }

        if ($contract->generated_by === $user->id) {
            return true;
        }

        return $contract->lead !== null && $this->canAccessLead($user, $contract->lead);
    }

    protected function canAccessLead(User $user, Lead $lead): bool
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
