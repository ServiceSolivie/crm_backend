<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private array $newPermissions = [
        'leads.view_gestion_assigned',
        'gestion.review',
        'gestion.flag_documents',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->newPermissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $superAdmin = Role::findByName('super_admin', 'web');
        $superAdmin->givePermissionTo($this->newPermissions);

        $gestion = Role::findOrCreate('gestion', 'web');
        $gestion->syncPermissions([
            ...$this->newPermissions,
            'leads.update_status',
            'lead_status_history.view',
            'lead_notes.manage',
            'documents.view',
            'documents.upload',
            'documents.download',
            'notifications.view',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findByName('gestion', 'web')?->delete();

        foreach ($this->newPermissions as $name) {
            Permission::where('name', $name)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
