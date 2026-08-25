<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Credential;
use App\Models\User;

class CredentialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::VAULT_CREDENTIALS_VIEW->value);
    }

    public function view(User $user, Credential $credential): bool
    {
        return $user->can(PermissionEnum::VAULT_CREDENTIALS_VIEW->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::VAULT_CREDENTIALS_CREATE->value);
    }

    public function update(User $user, Credential $credential): bool
    {
        return $user->can(PermissionEnum::VAULT_CREDENTIALS_UPDATE->value);
    }

    public function delete(User $user, Credential $credential): bool
    {
        return $user->can(PermissionEnum::VAULT_CREDENTIALS_DELETE->value);
    }

    public function assign(User $user): bool
    {
        return $user->can(PermissionEnum::VAULT_CREDENTIALS_ASSIGN->value);
    }
}
