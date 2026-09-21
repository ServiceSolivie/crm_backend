<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Gestion logs its Call2 verification calls on the lead, and can remove a
 * wrong document (e.g. a badly signed DVC) itself instead of only flagging it.
 * Payments stay hidden from gestion.
 */
return new class extends Migration
{
    private array $permissions = ['lead_calls.manage', 'documents.delete'];

    public function up(): void
    {
        foreach ($this->permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        Role::findOrCreate('gestion', 'web')->givePermissionTo($this->permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = Role::where('name', 'gestion')->where('guard_name', 'web')->first();

        foreach ($this->permissions as $name) {
            $role?->revokePermissionTo($name);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
