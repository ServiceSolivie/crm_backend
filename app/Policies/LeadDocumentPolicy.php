<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Lead;
use App\Models\LeadDocument;
use App\Models\User;

class LeadDocumentPolicy
{
    public function viewAny(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::DOCUMENTS_VIEW->value) && $this->canAccessLead($user, $lead);
    }

    public function upload(User $user, Lead $lead): bool
    {
        return $user->can(PermissionEnum::DOCUMENTS_UPLOAD->value) && $this->canAccessLead($user, $lead);
    }

    public function download(User $user, LeadDocument $document): bool
    {
        return $user->can(PermissionEnum::DOCUMENTS_DOWNLOAD->value) && $this->canAccessLead($user, $document->lead);
    }

    public function delete(User $user, LeadDocument $document): bool
    {
        return $user->can(PermissionEnum::DOCUMENTS_DELETE->value) && $this->canAccessLead($user, $document->lead);
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
