<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\ActivityOperation;
use App\Models\User;

/**
 * The activity journal shows payment amounts, client e-mails and technical
 * answers: the "audit_logs.view" permission (super_admin only by default).
 */
class ActivityOperationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::AUDIT_LOGS_VIEW->value);
    }

    public function view(User $user, ActivityOperation $operation): bool
    {
        return $this->viewAny($user);
    }
}
