<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

/**
 * Family of an activity log. An operation has the category of what it is
 * about (a payment, a Google Ads submission…); a refund log lives inside
 * its payment's operation.
 */
enum ActivityCategoryEnum: string implements HasLabel
{
    use EnumHelpers;

    case PAYMENT = 'payment';
    case REFUND = 'refund';
    case GOOGLE_ADS = 'google_ads';
    case AUTH = 'auth';
    case USER = 'user';
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::PAYMENT => 'Paiements',
            self::REFUND => 'Remboursements',
            self::GOOGLE_ADS => 'Google Ads',
            self::AUTH => 'Connexions',
            self::USER => 'Utilisateurs',
            self::SYSTEM => 'Système',
        };
    }
}
