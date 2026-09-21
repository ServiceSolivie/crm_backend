<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

/**
 * Status of one payment. Set by hand today (agent / gestion); later the
 * Hyperswitch webhook maps its events onto the same values:
 *   succeeded → REUSSI, processing / requires_* → EN_ATTENTE,
 *   failed → ECHOUE, cancelled → ANNULE, refund succeeded → REMBOURSE.
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

    /**
     * Statuses a payment can move to by hand from this one.
     *
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::EN_ATTENTE => [self::REUSSI, self::ECHOUE, self::ANNULE],
            self::REUSSI => [self::REMBOURSE],
            self::ECHOUE, self::ANNULE, self::REMBOURSE => [],
        };
    }
}
