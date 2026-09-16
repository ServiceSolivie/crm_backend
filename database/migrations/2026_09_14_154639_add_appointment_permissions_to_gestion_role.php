<?php

use Illuminate\Database\Migrations\Migration;
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

        Role::findByName('gestion', 'web')->givePermissionTo($this->permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findByName('gestion', 'web')?->revokePermissionTo($this->permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
