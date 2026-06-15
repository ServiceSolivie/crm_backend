<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\LeadSource;
use App\Models\User;

/**
 * Lead sources are a shared lookup list: any authenticated user may view
 * them (e.g. to populate a dropdown when creating a lead), but only
 * Super Admins may manage them.
 */
class LeadSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LeadSource $leadSource): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }

    public function update(User $user, LeadSource $leadSource): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }

    public function delete(User $user, LeadSource $leadSource): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }
}
