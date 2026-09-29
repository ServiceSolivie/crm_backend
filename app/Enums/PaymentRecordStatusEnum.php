<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

/**
 * Status of one payment. Payments come from Hyperswitch: the CRM pulls the
 * status (PaymentSyncService::syncFromProvider) and maps it with
 * fromProvider(). Older payments were entered by hand.
 *
 * Only REUSSI counts as money received.
 */
enum PaymentRecordStatusEnum: string implements HasLabel
{
    use EnumHelpers;

    case REUSSI = 'REUSSI';
    case EN_ATTENTE = 'EN_ATTENTE';
    case ECHOUE = 'ECHOUE';
    case ANNULE = 'ANNULE';
    case REMBOURSE = 'REMBOURSE';

    /**
     * Payment status for a Hyperswitch payment status. Null = no payment
     * to record (the client hasn't paid yet, or the link expired unused).
     */
    public static function fromProvider(?string $providerStatus): ?self
    {
        return match ($providerStatus) {
            // Only "succeeded" is money received (integration doc §6/§7)
            'succeeded' => self::REUSSI,
            'processing', 'pending' => self::EN_ATTENTE,
            'failed' => self::ECHOUE,
            'cancelled' => self::ANNULE,
            default => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::REUSSI => 'Reçu',
            self::EN_ATTENTE => 'En attente',
            self::ECHOUE => 'Échoué',
            self::ANNULE => 'Annulé',
            self::REMBOURSE => 'Remboursé',
        };
    }
}
