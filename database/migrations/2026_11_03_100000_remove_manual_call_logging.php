<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Calls are now only those Ringover reports (calls table): the calls logged
 * by hand on a lead and the permission to log them are removed, with the
 * rows already logged.
 */
return new class extends Migration
{
    private const PERMISSION = 'lead_calls.manage';

    /** Roles that had the permission before (RoleSeeder and the gestion migration) */
    private const ROLES = ['super_admin', 'manager', 'team_leader', 'agent', 'gestion'];

    public function up(): void
    {
        Schema::dropIfExists('lead_calls');

        Permission::where('name', self::PERMISSION)->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * The table comes back empty: the deleted rows cannot be restored.
     */
    public function down(): void
    {
        Schema::create('lead_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('outcome')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Permission::findOrCreate(self::PERMISSION, 'web');

        foreach (Role::whereIn('name', self::ROLES)->where('guard_name', 'web')->get() as $role) {
            $role->givePermissionTo(self::PERMISSION);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
