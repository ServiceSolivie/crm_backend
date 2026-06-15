<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\LeadImport;
use App\Models\User;

class LeadImportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::LEADS_IMPORT->value) || $user->can(PermissionEnum::LEADS_VIEW_ALL->value);
    }

    public function view(User $user, LeadImport $leadImport): bool
    {
        return $user->can(PermissionEnum::LEADS_VIEW_ALL->value) || $leadImport->imported_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::LEADS_IMPORT->value);
    }
}
