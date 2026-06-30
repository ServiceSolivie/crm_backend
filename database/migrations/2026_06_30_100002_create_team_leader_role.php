<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $teamLeader = Role::findOrCreate('team_leader', 'web');
        $teamLeader->syncPermissions([
            // Leads — team-scoped
            'leads.view_team',
            'leads.create',
            'leads.update',
            'leads.assign',
            'leads.update_status',
            'leads.export',

            // Lead management
            'lead_notes.manage',
            'lead_status_history.view',

            // Pipeline
            'pipeline.view',
            'pipeline.update_status',

            // Appointments — team-scoped
            'appointments.view_team',
            'appointments.create',
            'appointments.update',
            'appointments.delete',

            // Dashboard — team-scoped
            'dashboard.view_team',
            'dashboard.view_personal',

            // Reports — team-scoped
            'reports.view_team',
            'reports.export',

            // Revenue — team-scoped
            'revenue.view_team',
            'revenue.view_personal',
            'revenue.set',

            // Payments
            'payments.create',
            'payments.view',

            // Documents
            'documents.view',
            'documents.upload',
            'documents.download',

            // Notifications
            'notifications.view',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::findByName('team_leader', 'web');
        $role?->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
