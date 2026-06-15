<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\User;

class UserPolicy
{
    /**
     * Super Admins manage all users. A user may always view/update their own profile.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::USERS_VIEW->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(PermissionEnum::USERS_VIEW->value) || $user->is($model);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::USERS_CREATE->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(PermissionEnum::USERS_UPDATE->value) || $user->is($model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(PermissionEnum::USERS_DELETE->value) && ! $user->is($model);
    }

    /**
     * Only users with USERS_UPDATE may change another user's role.
     */
    public function assignRole(User $user, User $model): bool
    {
        return $user->can(PermissionEnum::USERS_UPDATE->value);
    }

    public function updateStatus(User $user, User $model): bool
    {
        return $user->can(PermissionEnum::USERS_UPDATE->value);
    }

    public function restore(User $user, User $model): bool
    {
        return $user->can(PermissionEnum::USERS_DELETE->value);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->can(PermissionEnum::USERS_DELETE->value) && ! $user->is($model);
    }
}
