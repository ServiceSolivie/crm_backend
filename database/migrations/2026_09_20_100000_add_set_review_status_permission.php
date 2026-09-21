<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Back-office statuses (Validé, Call2, PDG, À corriger) become restricted:
 * gestion, managers, team leaders and super admins keep them; agents no
 * longer have them and hand leads over by setting GESTION.
 */
return new class extends Migration
{
    private string $permission = 'leads.set_review_status';

    private array $roles = ['super_admin', 'manager', 'team_leader', 'gestion'];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate($this->permission, 'web');

        foreach ($this->roles as $roleName) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            $role?->givePermissionTo($this->permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', $this->permission)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
