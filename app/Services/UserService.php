<?php

namespace App\Services;

use App\Enums\RoleEnum;
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
        $sourceIds = $attributes['lead_source_ids'] ?? [];
        unset($attributes['role'], $attributes['lead_source_ids']);

        $attributes['password'] = Hash::make($attributes['password']);
        $attributes['is_active'] = $attributes['is_active'] ?? true;

        $user = $this->users->create($attributes);
        $user->syncRoles([$role]);
        $this->syncLeadSources($user, $sourceIds);

        return $user->load(['roles', 'leadSources']);
    }

    public function updateUser(User $user, array $attributes): User
    {
        // Password changes only ever go through resetPassword() below, which
        // enforces the super_admin-actor / non-super_admin-target rule.
        unset($attributes['password']);

        if (isset($attributes['role'])) {
            $user->syncRoles([$attributes['role']]);
            unset($attributes['role']);
        }

        $sourcesSent = array_key_exists('lead_source_ids', $attributes);
        $sourceIds = $attributes['lead_source_ids'] ?? [];
        unset($attributes['lead_source_ids']);

        $user->update($attributes);

        // Clear them when the role is no longer marketing, even if not sent
        if ($sourcesSent || ! $user->hasRole(RoleEnum::MARKETING->value)) {
            $this->syncLeadSources($user, $sourceIds);
        }

        return $user->refresh()->load(['roles', 'leadSources']);
    }

    public function deleteUser(User $user): bool
    {
        return (bool) $user->delete();
    }

    public function assignRole(User $user, string $role): User
    {
        $user->syncRoles([$role]);

        if ($role !== RoleEnum::MARKETING->value) {
            $this->syncLeadSources($user, []);
        }

        return $user->load('roles');
    }

    /**
     * Only marketing users keep lead sources; anyone else has none.
     *
     * @param  list<int>  $sourceIds
     */
    protected function syncLeadSources(User $user, array $sourceIds): void
    {
        $user->leadSources()->sync($user->hasRole(RoleEnum::MARKETING->value) ? $sourceIds : []);
    }

    public function resetPassword(User $target, string $newPassword): User
    {
        $target->update(['password' => Hash::make($newPassword)]);

        return $target->refresh();
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
