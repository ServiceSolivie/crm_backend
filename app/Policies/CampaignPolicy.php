<?php

namespace App\Policies;

use App\Enums\RoleEnum;
use App\Models\Campaign;
use App\Models\User;

class CampaignPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleEnum::SUPER_ADMIN->value);
    }

    public function view(User $user, Campaign $campaign): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Campaign $campaign): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Campaign $campaign): bool
    {
        return $this->viewAny($user);
    }
}
