<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

enum PaymentStatusEnum: string implements HasLabel
{
    use EnumHelpers;

    case NON_PAYE = 'NON_PAYE';
    case PARTIELLEMENT_PAYE = 'PARTIELLEMENT_PAYE';
    case PAYE = 'PAYE';

    public function label(): string
    {
        return match ($this) {
            self::NON_PAYE => 'Non payé',
            self::PARTIELLEMENT_PAYE => 'Partiellement payé',
            self::PAYE => 'Payé',
        };
    }
}
