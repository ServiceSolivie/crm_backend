<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

enum PaymentSourceEnum: string implements HasLabel
{
    use EnumHelpers;

    case MANUEL = 'MANUEL';
    case HYPERSWITCH = 'HYPERSWITCH';

    public function label(): string
    {
        return match ($this) {
            self::MANUEL => 'Saisie manuelle',
            self::HYPERSWITCH => 'Hyperswitch',
        };
    }
}
