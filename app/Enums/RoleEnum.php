<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

/**
 * System roles, registered via Spatie's laravel-permission.
 *
 * Values are used verbatim as the `name` column on the `roles` table.
 */
enum RoleEnum: string implements HasLabel
{
    use EnumHelpers;

    case SUPER_ADMIN = 'super_admin';
    case MANAGER = 'manager';
    case TEAM_LEADER = 'team_leader';
    case AGENT = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::MANAGER => 'Manager',
            self::TEAM_LEADER => 'Team Leader',
            self::AGENT => 'Agent',
        };
    }
}
