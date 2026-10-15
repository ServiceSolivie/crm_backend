<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

/**
 * Position of the "approved machines" switch.
 */
enum DeviceRestrictionEnum: string implements HasLabel
{
    use EnumHelpers;

    // Login as before: nothing is asked of the machine
    case OFF = 'off';
    // Rehearsal: everybody still logs in, machines register and try the PIN step
    case PREPARE = 'prepare';
    // Only approved machines log in (exempt roles excepted)
    case ON = 'on';

    public function label(): string
    {
        return match ($this) {
            self::OFF => 'Désactivée',
            self::PREPARE => 'Préparation',
            self::ON => 'Activée',
        };
    }
}
