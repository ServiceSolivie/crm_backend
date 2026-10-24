<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\User;

/**
 * The marketing intake (key and received requests) is the super admin's.
 */
class IntakeRequestPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }
}
