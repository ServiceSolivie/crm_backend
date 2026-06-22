<?php

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Create the three system roles and assign permissions
     * according to the approved permission matrix.
     */
    public function run(): void
    {
        $superAdmin = Role::findOrCreate(RoleEnum::SUPER_ADMIN->value);
        $superAdmin->syncPermissions(PermissionEnum::values());

        $manager = Role::findOrCreate(RoleEnum::MANAGER->value);
        $manager->syncPermissions([
            PermissionEnum::TEAMS_VIEW->value,
            PermissionEnum::TEAMS_MANAGE_MEMBERS->value,

            PermissionEnum::LEADS_VIEW_TEAM->value,
            PermissionEnum::LEADS_CREATE->value,
            PermissionEnum::LEADS_UPDATE->value,
            PermissionEnum::LEADS_ASSIGN->value,
            PermissionEnum::LEADS_UPDATE_STATUS->value,
            PermissionEnum::LEADS_IMPORT->value,
            PermissionEnum::LEADS_EXPORT->value,

            PermissionEnum::LEAD_NOTES_MANAGE->value,
            PermissionEnum::LEAD_STATUS_HISTORY_VIEW->value,

            PermissionEnum::PIPELINE_VIEW->value,
            PermissionEnum::PIPELINE_UPDATE_STATUS->value,

            PermissionEnum::APPOINTMENTS_VIEW_ALL->value,
            PermissionEnum::APPOINTMENTS_CREATE->value,
            PermissionEnum::APPOINTMENTS_UPDATE->value,
            PermissionEnum::APPOINTMENTS_DELETE->value,

            PermissionEnum::DASHBOARD_VIEW_TEAM->value,
            PermissionEnum::DASHBOARD_VIEW_PERSONAL->value,

            PermissionEnum::REPORTS_VIEW_TEAM->value,
            PermissionEnum::REPORTS_EXPORT->value,

            PermissionEnum::PAYMENTS_CREATE->value,
            PermissionEnum::PAYMENTS_VIEW->value,
            PermissionEnum::PAYMENTS_DELETE->value,

            PermissionEnum::REVENUE_VIEW_TEAM->value,
            PermissionEnum::REVENUE_VIEW_PERSONAL->value,
            PermissionEnum::REVENUE_SET->value,

            PermissionEnum::NOTIFICATIONS_VIEW->value,
        ]);

        $agent = Role::findOrCreate(RoleEnum::AGENT->value);
        $agent->syncPermissions([
            PermissionEnum::LEADS_VIEW_ASSIGNED->value,
            PermissionEnum::LEADS_UPDATE_STATUS->value,

            PermissionEnum::LEAD_NOTES_MANAGE->value,
            PermissionEnum::LEAD_STATUS_HISTORY_VIEW->value,

            PermissionEnum::PIPELINE_VIEW->value,
            PermissionEnum::PIPELINE_UPDATE_STATUS->value,

            PermissionEnum::APPOINTMENTS_VIEW_OWN->value,
            PermissionEnum::APPOINTMENTS_CREATE->value,
            PermissionEnum::APPOINTMENTS_UPDATE->value,
            PermissionEnum::APPOINTMENTS_DELETE->value,

            PermissionEnum::DASHBOARD_VIEW_PERSONAL->value,

            PermissionEnum::PAYMENTS_CREATE->value,
            PermissionEnum::PAYMENTS_VIEW->value,

            PermissionEnum::REVENUE_VIEW_PERSONAL->value,
            PermissionEnum::REVENUE_SET->value,

            PermissionEnum::NOTIFICATIONS_VIEW->value,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
