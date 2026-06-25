<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

enum InsuranceTypeEnum: string implements HasLabel
{
    use EnumHelpers;

    case AUTO = 'AUTO';
    case MOTO = 'MOTO';
    case RC_PRO = 'RC_PRO';
    case MUTUELLE_SANTE = 'MUTUELLE_SANTE';
    case EMPRUNTEUR = 'EMPRUNTEUR';
    case CREDIT_CONSOMMATION = 'CREDIT_CONSOMMATION';
    case RACHAT_CREDIT = 'RACHAT_CREDIT';
    case CREDIT_IMMOBILIER = 'CREDIT_IMMOBILIER';
    case DECENNALE = 'DECENNALE';
    case TAXI_VTC = 'TAXI_VTC';
    case AUTRE = 'AUTRE';

    public function label(): string
    {
        return match ($this) {
            self::AUTO => 'Auto Insurance',
            self::MOTO => 'Motorcycle Insurance',
            self::RC_PRO => 'Professional Liability',
            self::MUTUELLE_SANTE => 'Health Insurance',
            self::EMPRUNTEUR => 'Borrower Insurance',
            self::CREDIT_CONSOMMATION => 'Consumer Credit',
            self::RACHAT_CREDIT => 'Debt Consolidation',
            self::CREDIT_IMMOBILIER => 'Mortgage Credit',
            self::DECENNALE => 'Décennale',
            self::TAXI_VTC => 'Taxi / VTC',
            self::AUTRE => 'Autre',
        };
    }
}
