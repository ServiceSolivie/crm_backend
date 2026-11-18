<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Supervisor agents: an agent given this permission directly (user edit
 * page) also sees their team's numbers on the dashboard.
 */
return new class extends Migration
{
    private string $permission = 'dashboard.view_team_stats';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate($this->permission, 'web');
        Role::where('name', 'super_admin')->where('guard_name', 'web')->first()?->givePermissionTo($this->permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::where('name', $this->permission)->where('guard_name', 'web')->first()?->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
