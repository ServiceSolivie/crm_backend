<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\DvcType;
use App\Models\User;

/**
 * Everyone lists the active DVC types (an agent picks one on a lead); only
 * the super admin configures them, like the lead statuses.
 */
class DvcTypePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, DvcType $dvcType): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }

    public function update(User $user, DvcType $dvcType): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }

    public function delete(User $user, DvcType $dvcType): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }
}
