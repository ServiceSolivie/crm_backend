<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\LeadStatus;
use App\Models\User;

/**
 * Everyone reads the custom statuses (they show on leads); only the super
 * admin manages them, like the lead sources.
 */
class LeadStatusPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }

    public function update(User $user, LeadStatus $leadStatus): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }

    public function delete(User $user, LeadStatus $leadStatus): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }
}
