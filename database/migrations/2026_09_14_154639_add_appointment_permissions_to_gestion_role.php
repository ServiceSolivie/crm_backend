<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $permissions = [
        'appointments.view_own',
        'appointments.create',
        'appointments.update',
        'appointments.delete',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('gestion', 'web')->givePermissionTo($this->ensure($this->permissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Fresh installs run migrations before the permission seeder: create any
     * permission granted here that does not exist yet.
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    private function ensure(array $names): array
    {
        foreach ($names as $name) {
            Permission::findOrCreate($name, 'web');
        }

        return $names;
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findByName('gestion', 'web')?->revokePermissionTo($this->permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
