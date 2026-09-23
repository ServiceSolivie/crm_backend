<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The per-lead status-history / assignment-history endpoints are gone (the
 * lead page reads one /activity feed instead), so the permission that only
 * guarded them is retired. Deleting it detaches it from every role.
 */
return new class extends Migration
{
    private const NAME = 'lead_status_history.view';

    /** Roles that held it, restored by down() */
    private const ROLES = ['super_admin', 'manager', 'team_leader', 'agent', 'gestion'];

    public function up(): void
    {
        Permission::where('name', self::NAME)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $permission = Permission::findOrCreate(self::NAME, 'web');

        foreach (self::ROLES as $role) {
            Role::where('name', $role)->where('guard_name', 'web')->first()?->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
