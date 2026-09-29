<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Lead;
use App\Models\User;

/**
 * Payments are read-only in the CRM (they come from Hyperswitch). What a
 * user can do is see them, send / cancel a payment link, and change the
 * contract total of a lead.
 */
class PaymentPolicy
{
    public function viewAny(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::PAYMENTS_VIEW->value) && $this->canAccessLead($user, $lead);
    }

    /**
     * Payments page (all leads): the list itself is narrowed to the leads
     * the user can see by PaymentSessionService::paginateForUser().
     */
    public function viewList(User $user): bool
    {
        return $user->can(PermissionEnum::PAYMENTS_VIEW->value);
    }

    /**
     * Send (or cancel) a Hyperswitch payment link to the client. Allowed
     * before or after the DVC signature (PaymentSessionService refuses
     * lost leads).
     */
    public function managePaymentLinks(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::PAYMENTS_CREATE->value)
            && $this->canAccessLead($user, $lead);
    }

    /**
     * Change the contract total of a lead (e.g. raise it to ask for an
     * additional payment): the "revenue.set" permission, on a lead the
     * user can see.
     */
    public function updateTotal(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::REVENUE_SET->value)
            && $this->canAccessLead($user, $lead);
    }

    protected function canAccessLead(User $user, Lead $lead): bool
    {
        if ($user->can(PermissionEnum::LEADS_VIEW_ALL->value)) {
            return true;
        }

        if ($user->can(PermissionEnum::LEADS_VIEW_TEAM->value) && $lead->team_id !== null && $lead->team_id === $user->team_id) {
            return true;
        }

        if ($user->can(PermissionEnum::LEADS_VIEW_ASSIGNED->value) && $lead->assigned_to === $user->id) {
            return true;
        }

        if ($user->can(PermissionEnum::LEADS_VIEW_GESTION_ASSIGNED->value) && $lead->gestion_assigned_to === $user->id) {
            return true;
        }

        return false;
    }
}
