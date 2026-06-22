<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

enum PaymentMethodEnum: string implements HasLabel
{
    use EnumHelpers;

    case STRIPE = 'STRIPE';
    case VIREMENT_BANCAIRE = 'VIREMENT_BANCAIRE';
    case PAYMENT_LINK = 'PAYMENT_LINK';
    case AUTRE = 'AUTRE';

    public function label(): string
    {
        return match ($this) {
            self::STRIPE => 'Stripe',
            self::VIREMENT_BANCAIRE => 'Virement bancaire',
            self::PAYMENT_LINK => 'Lien de paiement',
            self::AUTRE => 'Autre',
        };
    }
}
