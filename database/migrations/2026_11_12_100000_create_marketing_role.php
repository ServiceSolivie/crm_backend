<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Marketing: read-only access to the global dashboard and the reports.
 */
return new class extends Migration
{
    private array $permissions = [
        'dashboard.view_global',
        'reports.view_all',
        'reports.export',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Fresh installs run migrations before the permission seeder
        foreach ($this->permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate('marketing', 'web')->syncPermissions($this->permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::where('name', 'marketing')->where('guard_name', 'web')->first()?->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
