<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Filters\UserFilter;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(protected UserRepositoryInterface $users) {}

    public function paginateFiltered(UserFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->users->paginateFiltered($filters, $perPage);
    }

    public function createUser(array $attributes): User
    {
        $role = $attributes['role'];
        unset($attributes['role']);

        $attributes['password'] = Hash::make($attributes['password']);
        $attributes['is_active'] = $attributes['is_active'] ?? true;

        $user = $this->users->create($attributes);
        $user->syncRoles([$role]);

        return $user->load('roles');
    }

    public function updateUser(User $user, array $attributes): User
    {
        if (isset($attributes['password'])) {
            $attributes['password'] = Hash::make($attributes['password']);
        }

        $user->update($attributes);

        return $user->refresh();
    }

    public function deleteUser(User $user): bool
    {
        return (bool) $user->delete();
    }

    public function assignRole(User $user, string $role): User
    {
        $user->syncRoles([$role]);

        return $user->load('roles');
    }

    public function setActive(User $actingUser, User $target, bool $isActive): User
    {
        if ($actingUser->is($target) && ! $isActive) {
            throw new ApiException('You cannot deactivate your own account.', 422);
        }

        $target->update(['is_active' => $isActive]);

        return $target->refresh();
    }
}
