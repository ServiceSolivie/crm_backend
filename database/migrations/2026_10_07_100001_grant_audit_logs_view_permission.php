<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * The activity journal shows payment amounts, client e-mails and technical
 * answers: it uses the "audit_logs.view" permission (already declared, not
 * used until now), given only to super_admin.
 */
return new class extends Migration
{
    private const PERMISSION = 'audit_logs.view';

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::findOrCreate(self::PERMISSION, 'web');
        Role::findOrCreate('super_admin', 'web')->givePermissionTo(self::PERMISSION);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // The permission existed before this migration: it is kept.
    }
};
