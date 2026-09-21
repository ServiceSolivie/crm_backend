<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'documents.view',
            'documents.upload',
            'documents.delete',
            'documents.download',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $superAdmin = Role::findOrCreate('super_admin', 'web');
        $superAdmin->givePermissionTo($permissions);

        $manager = Role::findOrCreate('manager', 'web');
        $manager->givePermissionTo($permissions);

        $agent = Role::findOrCreate('agent', 'web');
        $agent->givePermissionTo([
            'documents.view',
            'documents.upload',
            'documents.download',
        ]);
    }

    public function down(): void
    {
        $permissions = [
            'documents.view',
            'documents.upload',
            'documents.delete',
            'documents.download',
        ];

        foreach ($permissions as $permission) {
            Permission::where('name', $permission)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
