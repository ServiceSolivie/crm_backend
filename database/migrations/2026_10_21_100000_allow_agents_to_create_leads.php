<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Agents may create leads (they get them assigned to themselves, see
     * LeadService::createFromForm). Deploys don't run the role seeder, so
     * existing databases get the permission here.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Fresh installs run migrations before the seeders: no role yet
        $agent = Role::where('name', 'agent')->where('guard_name', 'web')->first();
        $agent?->givePermissionTo(Permission::findOrCreate('leads.create', 'web'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $agent = Role::where('name', 'agent')->where('guard_name', 'web')->first();
        if ($agent?->hasPermissionTo('leads.create')) {
            $agent->revokePermissionTo('leads.create');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
