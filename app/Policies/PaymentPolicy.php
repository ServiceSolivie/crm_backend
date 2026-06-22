<?php

namespace App\Policies;

use App\Enums\LeadStatusEnum;
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

    public function create(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::PAYMENTS_CREATE->value)
            && $lead->status === LeadStatusEnum::VALIDE
            && $lead->expected_revenue !== null
            && $this->canAccessLead($user, $lead);
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

        return false;
    }
}
