<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private array $permissions = [
        'vault.partners.view',
        'vault.partners.create',
        'vault.partners.update',
        'vault.partners.delete',
        'vault.credentials.view',
        'vault.credentials.create',
        'vault.credentials.update',
        'vault.credentials.delete',
        'vault.credentials.assign',
        'vault.audit_logs.view',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $superAdmin = Role::findByName('super_admin', 'web');
        $superAdmin->givePermissionTo($this->permissions);
    }

    public function down(): void
    {
        foreach ($this->permissions as $name) {
            Permission::where('name', $name)->delete();
        }
    }
};
