<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\User;

class DocumentTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::DOCUMENT_REQUIREMENTS_VIEW->value);
    }

    public function manage(User $user): bool
    {
        return $user->can(PermissionEnum::DOCUMENT_REQUIREMENTS_MANAGE->value);
    }
}
