<?php

namespace App\Policies;

use App\Enums\PaymentRecordStatusEnum;
use App\Enums\PermissionEnum;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::PAYMENTS_VIEW->value) && $this->canAccessLead($user, $lead);
    }

    /**
     * Payments no longer wait for Validé: they can be recorded before or
     * after the DVC signature (PaymentService refuses lost leads).
     */
    public function create(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::PAYMENTS_CREATE->value)
            && $this->canAccessLead($user, $lead);
    }

    /**
     * Mark a payment received / failed / cancelled; a refund also needs
     * the right to delete payments (managers, admins).
     */
    public function updateStatus(User $user, Payment $payment, PaymentRecordStatusEnum $to): bool
    {
        if (! $user->can(PermissionEnum::PAYMENTS_CREATE->value) || ! $this->canAccessLead($user, $payment->lead)) {
            return false;
        }

        return $to !== PaymentRecordStatusEnum::REMBOURSE || $user->can(PermissionEnum::PAYMENTS_DELETE->value);
    }

    public function delete(User $user, Payment $payment): bool
    {
        return $user->can(PermissionEnum::PAYMENTS_DELETE->value)
            && $this->canAccessLead($user, $payment->lead);
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
