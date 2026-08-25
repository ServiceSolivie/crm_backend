<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Partner;
use App\Models\User;

class PartnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::VAULT_PARTNERS_VIEW->value);
    }

    public function view(User $user, Partner $partner): bool
    {
        return $user->can(PermissionEnum::VAULT_PARTNERS_VIEW->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::VAULT_PARTNERS_CREATE->value);
    }

    public function update(User $user, Partner $partner): bool
    {
        return $user->can(PermissionEnum::VAULT_PARTNERS_UPDATE->value);
    }

    public function delete(User $user, Partner $partner): bool
    {
        return $user->can(PermissionEnum::VAULT_PARTNERS_DELETE->value);
    }
}
