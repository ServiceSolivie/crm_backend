<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

/**
 * Payment status of a lead, recalculated by PaymentService from its
 * payments (see PaymentRecordStatusEnum).
 */
enum PaymentStatusEnum: string implements HasLabel
{
    use EnumHelpers;

    case NON_PAYE = 'NON_PAYE';
    case EN_ATTENTE = 'EN_ATTENTE';
    case PARTIELLEMENT_PAYE = 'PARTIELLEMENT_PAYE';
    case PAYE = 'PAYE';
    case REMBOURSE = 'REMBOURSE';

    public function label(): string
    {
        return match ($this) {
            self::NON_PAYE => 'Non payé',
            self::EN_ATTENTE => 'Paiement en attente',
            self::PARTIELLEMENT_PAYE => 'Partiellement payé',
            self::PAYE => 'Payé',
            self::REMBOURSE => 'Remboursé',
        };
    }
}
