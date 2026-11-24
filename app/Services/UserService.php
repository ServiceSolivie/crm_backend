<?php

namespace App\Services;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Exceptions\ApiException;
use App\Filters\UserFilter;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\ActivityLogger;
use App\Support\SlackAlert;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UserService
{
    public function __construct(protected UserRepositoryInterface $users) {}

    public function paginateFiltered(UserFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        // The technical import account is not a person: not listed
        return $this->users->paginateFiltered($filters, $perPage, fn ($query) => $query->where('email', '!=', User::SYSTEM_EMAIL));
    }

    public function createUser(array $attributes): User
    {
        $role = $attributes['role'];
        $sourceIds = $attributes['lead_source_ids'] ?? [];
        $supervisor = (bool) ($attributes['is_supervisor'] ?? false);
        unset($attributes['role'], $attributes['lead_source_ids'], $attributes['is_supervisor']);

        $attributes['password'] = Hash::make($attributes['password']);
        $attributes['is_active'] = $attributes['is_active'] ?? true;

        $user = $this->users->create($attributes);
        $user->syncRoles([$role]);
        $this->syncLeadSources($user, $sourceIds);
        $this->syncSupervisor($user, $supervisor);

        if ($role === RoleEnum::SUPER_ADMIN->value) {
            SlackAlert::send('Nouveau super admin créé', ['Compte' => "{$user->name} (#{$user->id}) — {$user->email}"]);
        }

        return $user->load(['roles', 'leadSources']);
    }

    public function updateUser(User $user, array $attributes): User
    {
        // Password changes only ever go through resetPassword() below, which
        // enforces the super_admin-actor / non-super_admin-target rule.
        unset($attributes['password']);

        $oldRole = $user->roles->first()?->name;
        $wasActive = (bool) $user->is_active;

        if (isset($attributes['role'])) {
            $user->syncRoles([$attributes['role']]);
            unset($attributes['role']);
        }

        $sourcesSent = array_key_exists('lead_source_ids', $attributes);
        $sourceIds = $attributes['lead_source_ids'] ?? [];
        unset($attributes['lead_source_ids']);

        $supervisorSent = array_key_exists('is_supervisor', $attributes);
        $supervisor = (bool) ($attributes['is_supervisor'] ?? false);
        unset($attributes['is_supervisor']);

        $user->update($attributes);

        // Only an agent can be a supervisor: dropped when the role changes
        if ($supervisorSent || ! $user->hasRole(RoleEnum::AGENT->value)) {
            $this->syncSupervisor($user, $supervisor);
        }

        // Clear them when the role is no longer marketing, even if not sent
        if ($sourcesSent || ! $user->hasRole(RoleEnum::MARKETING->value)) {
            $this->syncLeadSources($user, $sourceIds);
        }

        $user->refresh()->load(['roles', 'leadSources']);
        $this->alertRoleChange($user, $oldRole);
        if ($wasActive !== (bool) $user->is_active) {
            $this->alertActiveChange($user);
        }

        return $user;
    }

    /**
     * Where a user's work is recorded. Deleting the user would delete these
     * rows with him (the database cascades), and with a lead go its notes,
     * history, appointments, payments and documents.
     */
    protected const OWNED_DATA = [
        'leads' => ['created_by', 'assigned_to', 'gestion_assigned_to'],
        'lead_notes' => ['user_id'],
        'lead_status_histories' => ['changed_by'],
        'lead_assignment_histories' => ['to_user_id', 'assigned_by'],
        'appointments' => ['agent_id', 'created_by'],
        'payments' => ['created_by'],
        'lead_documents' => ['uploaded_by'],
        'lead_imports' => ['imported_by'],
        'contracts' => ['generated_by'],
        'calls' => ['user_id'],
    ];

    /**
     * A user who has worked in the CRM is never deleted: that would wipe his
     * leads and everything attached to them. He is deactivated instead.
     *
     * @throws ApiException
     */
    public function deleteUser(User $user): bool
    {
        $leads = DB::table('leads')->where(fn ($q) => $q->where('created_by', $user->id)
            ->orWhere('assigned_to', $user->id)->orWhere('gestion_assigned_to', $user->id))->count();

        if ($leads > 0 || $this->hasOwnedData($user)) {
            SlackAlert::send('Suppression d’utilisateur refusée', ['Compte' => "{$user->name} (#{$user->id})", 'Leads liés' => $leads]);

            throw new ApiException(
                $leads > 0
                    ? "Impossible de supprimer {$user->name} : {$leads} lead(s) lui sont liés et seraient supprimés avec lui. Désactivez plutôt ce compte."
                    : "Impossible de supprimer {$user->name} : des données du CRM lui sont liées. Désactivez plutôt ce compte.",
                409,
            );
        }

        $deleted = (bool) $user->delete();
        SlackAlert::send('Utilisateur supprimé', ['Compte' => "{$user->name} (#{$user->id}) — {$user->email}"]);

        return $deleted;
    }

    protected function alertRoleChange(User $user, ?string $oldRole): void
    {
        $newRole = $user->roles->first()?->name;
        if ($oldRole !== $newRole) {
            SlackAlert::send('Rôle modifié', ['Compte' => "{$user->name} (#{$user->id})", 'Avant' => $oldRole ?? '—', 'Après' => $newRole ?? '—']);
        }
    }

    protected function alertActiveChange(User $user): void
    {
        SlackAlert::send($user->is_active ? 'Utilisateur réactivé' : 'Utilisateur désactivé', ['Compte' => "{$user->name} (#{$user->id})"]);
    }

    protected function hasOwnedData(User $user): bool
    {
        foreach (self::OWNED_DATA as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $columns = array_filter($columns, fn (string $column) => Schema::hasColumn($table, $column));
            if ($columns && DB::table($table)->where(function ($q) use ($columns, $user) {
                foreach ($columns as $column) {
                    $q->orWhere($column, $user->id);
                }
            })->exists()) {
                return true;
            }
        }

        return false;
    }

    public function assignRole(User $user, string $role): User
    {
        $oldRole = $user->roles->first()?->name;
        $user->syncRoles([$role]);

        if ($role !== RoleEnum::MARKETING->value) {
            $this->syncLeadSources($user, []);
        }
        if ($role !== RoleEnum::AGENT->value) {
            $this->syncSupervisor($user, false);
        }

        $user->load('roles');
        $this->alertRoleChange($user, $oldRole);

        return $user;
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

    /**
     * A supervisor is an agent who also sees their team's numbers on the
     * dashboard: the permission is given to the user directly, not the role.
     */
    protected function syncSupervisor(User $user, bool $supervisor): void
    {
        $permission = PermissionEnum::DASHBOARD_VIEW_TEAM_STATS->value;

        if ($supervisor && $user->hasRole(RoleEnum::AGENT->value)) {
            $user->givePermissionTo($permission);
        } elseif ($user->getDirectPermissions()->contains('name', $permission)) {
            $user->revokePermissionTo($permission);
        }
    }

    public function resetPassword(User $target, string $newPassword): User
    {
        $target->update(['password' => Hash::make($newPassword)]);
        ActivityLogger::log('auth', 'auth.password_reset', $target, 'Mot de passe réinitialisé par un administrateur');

        return $target->refresh();
    }

    public function setActive(User $actingUser, User $target, bool $isActive): User
    {
        if ($actingUser->is($target) && ! $isActive) {
            throw new ApiException('You cannot deactivate your own account.', 422);
        }

        if ($target->isSystem()) {
            throw new ApiException('Le compte technique « Import automatique » ne peut pas être activé.', 422);
        }

        $was = (bool) $target->is_active;
        $target->update(['is_active' => $isActive]);
        if ($was !== $isActive) {
            $this->alertActiveChange($target);
        }

        return $target->refresh();
    }
}
