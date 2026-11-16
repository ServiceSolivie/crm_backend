<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\InsuranceType;
use App\Models\User;

/**
 * Everyone reads the insurance types (they show on leads); only the super
 * admin manages them, like the lead sources and statuses.
 */
class InsuranceTypePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }

    public function update(User $user, InsuranceType $insuranceType): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }

    public function delete(User $user, InsuranceType $insuranceType): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }
}
