<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

/**
 * System-wide permission catalog, registered via Spatie's laravel-permission.
 *
 * Values are used verbatim as the `name` column on the `permissions` table.
 * Mirrors the permission matrix from the approved architecture report.
 *
 * Permissions for modules not yet implemented (Leads, Appointments, ...)
 * are pre-provisioned here so role assignments don't need to change again
 * once those modules are built.
 */
enum PermissionEnum: string implements HasLabel
{
    use EnumHelpers;

    // Users
    case USERS_VIEW = 'users.view';
    case USERS_CREATE = 'users.create';
    case USERS_UPDATE = 'users.update';
    case USERS_DELETE = 'users.delete';

    // Teams
    case TEAMS_VIEW = 'teams.view';
    case TEAMS_CREATE = 'teams.create';
    case TEAMS_UPDATE = 'teams.update';
    case TEAMS_DELETE = 'teams.delete';
    case TEAMS_MANAGE_MEMBERS = 'teams.manage_members';

    // Leads
    case LEADS_VIEW_ALL = 'leads.view_all';
    case LEADS_VIEW_TEAM = 'leads.view_team';
    case LEADS_VIEW_ASSIGNED = 'leads.view_assigned';
    case LEADS_CREATE = 'leads.create';
    case LEADS_UPDATE = 'leads.update';
    case LEADS_DELETE = 'leads.delete';
    case LEADS_ASSIGN = 'leads.assign';
    case LEADS_UPDATE_STATUS = 'leads.update_status';
    case LEADS_IMPORT = 'leads.import';
    case LEADS_EXPORT = 'leads.export';

    // Lead notes & status history
    case LEAD_NOTES_MANAGE = 'lead_notes.manage';
    case LEAD_STATUS_HISTORY_VIEW = 'lead_status_history.view';

    // Pipeline
    case PIPELINE_VIEW = 'pipeline.view';
    case PIPELINE_UPDATE_STATUS = 'pipeline.update_status';

    // Appointments
    case APPOINTMENTS_VIEW_ALL = 'appointments.view_all';
    case APPOINTMENTS_VIEW_TEAM = 'appointments.view_team';
    case APPOINTMENTS_VIEW_OWN = 'appointments.view_own';
    case APPOINTMENTS_CREATE = 'appointments.create';
    case APPOINTMENTS_UPDATE = 'appointments.update';
    case APPOINTMENTS_DELETE = 'appointments.delete';

    // Dashboard
    case DASHBOARD_VIEW_GLOBAL = 'dashboard.view_global';
    case DASHBOARD_VIEW_TEAM = 'dashboard.view_team';
    case DASHBOARD_VIEW_PERSONAL = 'dashboard.view_personal';

    // Reports
    case REPORTS_VIEW_ALL = 'reports.view_all';
    case REPORTS_VIEW_TEAM = 'reports.view_team';
    case REPORTS_EXPORT = 'reports.export';

    // Payments
    case PAYMENTS_CREATE = 'payments.create';
    case PAYMENTS_VIEW = 'payments.view';
    case PAYMENTS_DELETE = 'payments.delete';

    // Revenue
    case REVENUE_VIEW_ALL = 'revenue.view_all';
    case REVENUE_VIEW_TEAM = 'revenue.view_team';
    case REVENUE_VIEW_PERSONAL = 'revenue.view_personal';
    case REVENUE_SET = 'revenue.set';

    // Documents
    case DOCUMENTS_VIEW = 'documents.view';
    case DOCUMENTS_UPLOAD = 'documents.upload';
    case DOCUMENTS_DELETE = 'documents.delete';
    case DOCUMENTS_DOWNLOAD = 'documents.download';

    // Notifications
    case NOTIFICATIONS_VIEW = 'notifications.view';

    // Audit logs
    case AUDIT_LOGS_VIEW = 'audit_logs.view';

    public function label(): string
    {
        return match ($this) {
            self::USERS_VIEW => 'View users',
            self::USERS_CREATE => 'Create users',
            self::USERS_UPDATE => 'Update users',
            self::USERS_DELETE => 'Delete users',

            self::TEAMS_VIEW => 'View teams',
            self::TEAMS_CREATE => 'Create teams',
            self::TEAMS_UPDATE => 'Update teams',
            self::TEAMS_DELETE => 'Delete teams',
            self::TEAMS_MANAGE_MEMBERS => 'Manage team members',

            self::LEADS_VIEW_ALL => 'View all leads',
            self::LEADS_VIEW_TEAM => 'View team leads',
            self::LEADS_VIEW_ASSIGNED => 'View assigned leads',
            self::LEADS_CREATE => 'Create leads',
            self::LEADS_UPDATE => 'Update leads',
            self::LEADS_DELETE => 'Delete leads',
            self::LEADS_ASSIGN => 'Assign leads',
            self::LEADS_UPDATE_STATUS => 'Update lead status',
            self::LEADS_IMPORT => 'Import leads',
            self::LEADS_EXPORT => 'Export leads',

            self::LEAD_NOTES_MANAGE => 'Manage lead notes',
            self::LEAD_STATUS_HISTORY_VIEW => 'View lead status history',

            self::PIPELINE_VIEW => 'View pipeline',
            self::PIPELINE_UPDATE_STATUS => 'Move leads in pipeline',

            self::APPOINTMENTS_VIEW_ALL => 'View all appointments',
            self::APPOINTMENTS_VIEW_TEAM => 'View team appointments',
            self::APPOINTMENTS_VIEW_OWN => 'View own appointments',
            self::APPOINTMENTS_CREATE => 'Create appointments',
            self::APPOINTMENTS_UPDATE => 'Update appointments',
            self::APPOINTMENTS_DELETE => 'Delete appointments',

            self::DASHBOARD_VIEW_GLOBAL => 'View global dashboard',
            self::DASHBOARD_VIEW_TEAM => 'View team dashboard',
            self::DASHBOARD_VIEW_PERSONAL => 'View personal dashboard',

            self::REPORTS_VIEW_ALL => 'View all reports',
            self::REPORTS_VIEW_TEAM => 'View team reports',
            self::REPORTS_EXPORT => 'Export reports',

            self::PAYMENTS_CREATE => 'Enregistrer des paiements',
            self::PAYMENTS_VIEW => 'Voir les paiements',
            self::PAYMENTS_DELETE => 'Supprimer des paiements',

            self::REVENUE_VIEW_ALL => 'Voir le chiffre d\'affaires global',
            self::REVENUE_VIEW_TEAM => 'Voir le chiffre d\'affaires équipe',
            self::REVENUE_VIEW_PERSONAL => 'Voir son chiffre d\'affaires',
            self::REVENUE_SET => 'Définir le montant attendu',

            self::DOCUMENTS_VIEW => 'Voir les documents',
            self::DOCUMENTS_UPLOAD => 'Téléverser des documents',
            self::DOCUMENTS_DELETE => 'Supprimer des documents',
            self::DOCUMENTS_DOWNLOAD => 'Télécharger des documents',

            self::NOTIFICATIONS_VIEW => 'View notifications',

            self::AUDIT_LOGS_VIEW => 'View audit logs',
        };
    }
}
