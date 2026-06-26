<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

enum ClientTypeEnum: string implements HasLabel
{
    use EnumHelpers;

    case INDIVIDUAL = 'INDIVIDUAL';
    case PROFESSIONAL = 'PROFESSIONAL';

    public function label(): string
    {
        return match ($this) {
            self::INDIVIDUAL => 'Particulier',
            self::PROFESSIONAL => 'Professionnel',
        };
    }
}
