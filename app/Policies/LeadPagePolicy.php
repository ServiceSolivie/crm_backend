<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\LeadPage;
use App\Models\User;

/**
 * Everyone reads the lead pages (they build the menu); only the super
 * admin manages them.
 */
class LeadPagePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }

    public function update(User $user, LeadPage $leadPage): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }

    public function delete(User $user, LeadPage $leadPage): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }
}
