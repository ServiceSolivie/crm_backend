<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

enum TrustedDeviceStatusEnum: string implements HasLabel
{
    use EnumHelpers;

    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REVOKED = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'En attente',
            self::APPROVED => 'Approuvé',
            self::REVOKED => 'Révoqué',
        };
    }
}
