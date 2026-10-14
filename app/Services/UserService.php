<?php

namespace App\Services;

use App\Enums\ActivityEventEnum;
use App\Exceptions\ApiException;
use App\Filters\UserFilter;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function __construct(protected UserRepositoryInterface $users, protected ActivityLogger $activity) {}

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

        $this->activity->user($user, ActivityEventEnum::USER_CREATED, "Compte créé avec le rôle « {$role} ».", ['role' => $role]);

        return $user->load('roles');
    }

    public function updateUser(User $user, array $attributes): User
    {
        // Password changes only ever go through resetPassword() below, which
        // enforces the super_admin-actor / non-super_admin-target rule.
        unset($attributes['password']);

        if (isset($attributes['role'])) {
            $previousRole = $user->getRoleNames()->first();
            $user->syncRoles([$attributes['role']]);
            $this->logRoleChange($user, $previousRole, $attributes['role']);
            unset($attributes['role']);
        }

        $user->update($attributes);

        // Which fields changed, never their values
        $fields = array_values(array_diff(array_keys($user->getChanges()), ['updated_at']));
        if ($fields) {
            $this->activity->user($user, ActivityEventEnum::USER_UPDATED, 'Champs modifiés : '.implode(', ', $fields).'.', ['fields' => $fields]);
        }

        return $user->refresh()->load('roles');
    }

    public function deleteUser(User $user): bool
    {
        $deleted = (bool) $user->delete();

        if ($deleted) {
            $this->activity->user($user, ActivityEventEnum::USER_DELETED, 'Compte supprimé.');
        }

        return $deleted;
    }

    public function assignRole(User $user, string $role): User
    {
        $previousRole = $user->getRoleNames()->first();
        $user->syncRoles([$role]);
        $this->logRoleChange($user, $previousRole, $role);

        return $user->load('roles');
    }

    public function resetPassword(User $target, string $newPassword): User
    {
        $target->update(['password' => Hash::make($newPassword)]);

        $this->activity->user($target, ActivityEventEnum::USER_PASSWORD_RESET, 'Mot de passe réinitialisé par un administrateur.');

        return $target->refresh();
    }

    public function setActive(User $actingUser, User $target, bool $isActive): User
    {
        if ($actingUser->is($target) && ! $isActive) {
            throw new ApiException('You cannot deactivate your own account.', 422);
        }

        $target->update(['is_active' => $isActive]);

        $this->activity->user(
            $target,
            $isActive ? ActivityEventEnum::USER_ACTIVATED : ActivityEventEnum::USER_DEACTIVATED,
            $isActive ? 'Compte réactivé : il peut de nouveau se connecter.' : 'Compte désactivé : il ne peut plus se connecter.',
            [],
            $actingUser,
        );

        return $target->refresh();
    }

    protected function logRoleChange(User $user, ?string $previous, string $role): void
    {
        if ($previous === $role) {
            return;
        }

        $this->activity->user($user, ActivityEventEnum::USER_ROLE_CHANGED, ($previous ?? 'aucun rôle')." → {$role}", ['previous_role' => $previous, 'role' => $role]);
    }
}
