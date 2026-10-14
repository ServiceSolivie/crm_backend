<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\User;

/**
 * Approving machines decides who can open the CRM: super admin only.
 */
class TrustedDevicePolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }
}
