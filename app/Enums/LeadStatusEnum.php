<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

enum LeadStatusEnum: string implements HasLabel
{
    use EnumHelpers;

    case NRP = 'NRP';
    case VALIDE = 'VALIDE';
    case RAPPEL = 'RAPPEL';
    case RENDEZ_VOUS_ASSURE = 'RENDEZ_VOUS_ASSURE';
    case PAS_INTERESSEE = 'PAS_INTERESSEE';

    public function label(): string
    {
        return match ($this) {
            self::NRP => 'No Reply / Not Reachable',
            self::VALIDE => 'Validated',
            self::RAPPEL => 'Callback Scheduled',
            self::RENDEZ_VOUS_ASSURE => 'Appointment Confirmed',
            self::PAS_INTERESSEE => 'Not Interested',
        };
    }
}
