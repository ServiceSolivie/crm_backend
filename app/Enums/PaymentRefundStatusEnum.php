<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

/**
 * Status of one refund of a paid payment session (Hyperswitch refund).
 *
 * Sogecommerce decides by itself: before settlement it cancels the debit
 * (succeeded at once), after settlement it issues a credit (pending until
 * the bank settles it, then succeeded).
 */
enum PaymentRefundStatusEnum: string implements HasLabel
{
    use EnumHelpers;

    // Saved before calling Hyperswitch, or Hyperswitch gave no clear answer:
    // the refund may exist, check it with its refund_id (never create another)
    case A_VERIFIER = 'A_VERIFIER';
    // Credit accepted by the bank, not settled yet
    case EN_ATTENTE = 'EN_ATTENTE';
    case REUSSI = 'REUSSI';
    case ECHOUE = 'ECHOUE';

    /**
     * Refunds that block a new refund of the same session.
     *
     * @return array<int, self>
     */
    public static function blocking(): array
    {
        return [self::A_VERIFIER, self::EN_ATTENTE, self::REUSSI];
    }

    /**
     * Refunds still followed with Hyperswitch.
     *
     * @return array<int, self>
     */
    public static function pending(): array
    {
        return [self::A_VERIFIER, self::EN_ATTENTE];
    }

    /**
     * Refund status for a Hyperswitch refund status; null = unknown value,
     * keep the current status.
     */
    public static function fromProvider(?string $providerStatus): ?self
    {
        return match ($providerStatus) {
            'succeeded' => self::REUSSI,
            'pending', 'review', 'manual_review' => self::EN_ATTENTE,
            'failed', 'transaction_failure' => self::ECHOUE,
            default => null,
        };
    }

    public function isFinal(): bool
    {
        return ! in_array($this, self::pending(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::A_VERIFIER => 'À vérifier',
            self::EN_ATTENTE => 'Remboursement en cours',
            self::REUSSI => 'Remboursé',
            self::ECHOUE => 'Remboursement échoué',
        };
    }
}
