<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

enum AppointmentStatusEnum: string implements HasLabel
{
    use EnumHelpers;

    case PLANIFIE = 'PLANIFIE';
    case REALISE = 'REALISE';
    case ANNULE = 'ANNULE';
    case REPORTE = 'REPORTE';

    public function label(): string
    {
        return match ($this) {
            self::PLANIFIE => 'Scheduled',
            self::REALISE => 'Completed',
            self::ANNULE => 'Cancelled',
            self::REPORTE => 'Rescheduled',
        };
    }
}
