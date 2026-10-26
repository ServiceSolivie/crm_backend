<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Agents see and listen to their own calls only; managers and team leaders
     * see their team's calls; super admins see everything.
     */
    protected array $matrix = [
        'super_admin' => [
            'calls.make', 'calls.view_own', 'calls.view_team', 'calls.view_all',
            'calls.listen_own', 'calls.listen_team', 'calls.listen_all',
            'ringover.manage',
        ],
        'manager' => [
            'calls.make', 'calls.view_own', 'calls.view_team',
            'calls.listen_own', 'calls.listen_team',
        ],
        'team_leader' => [
            'calls.make', 'calls.view_own', 'calls.view_team',
            'calls.listen_own', 'calls.listen_team',
        ],
        'agent' => [
            'calls.make', 'calls.view_own', 'calls.listen_own',
        ],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->matrix['super_admin'] as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach ($this->matrix as $roleName => $permissions) {
            Role::findOrCreate($roleName, 'web')->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::whereIn('name', $this->matrix['super_admin'])->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
