<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

enum AppointmentStatusEnum: string implements HasLabel
{
    use EnumHelpers;

    case PLANIFIE = 'PLANIFIE';
    case CONFIRME = 'CONFIRME';
    case REALISE = 'REALISE';
    case ANNULE = 'ANNULE';
    case REPORTE = 'REPORTE';
    case NON_VENU = 'NON_VENU';

    /**
     * Appointments still to happen (not done, cancelled or missed).
     *
     * @return array<int, string>
     */
    public static function openValues(): array
    {
        return [self::PLANIFIE->value, self::CONFIRME->value];
    }

    public function label(): string
    {
        return match ($this) {
            self::PLANIFIE => 'Planifié',
            self::CONFIRME => 'Confirmé',
            self::REALISE => 'Réalisé',
            self::ANNULE => 'Annulé',
            self::REPORTE => 'Reporté',
            self::NON_VENU => 'Non Venu',
        };
    }
}
