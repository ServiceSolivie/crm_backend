<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

/**
 * Status of a payment session: one payment request sent to the client
 * (one Hyperswitch payment, one link). Its attempts are recorded as
 * payments grouped under it (see PaymentSyncService::syncFromProvider).
 */
enum PaymentSessionStatusEnum: string implements HasLabel
{
    use EnumHelpers;

    case OUVERTE = 'OUVERTE';
    // Hyperswitch did not answer clearly: the payment may exist, to check
    // with its payment_id before anything else (never create a second one)
    case A_VERIFIER = 'A_VERIFIER';
    case PAYEE = 'PAYEE';
    // The payment failed (card refused, 3DS failed…): the Hyperswitch
    // payment is final, the agent sends a new link
    case ECHOUEE = 'ECHOUEE';
    case ANNULEE = 'ANNULEE';
    case EXPIREE = 'EXPIREE';

    /**
     * Statuses that block sending another link for the same lead.
     *
     * @return array<int, self>
     */
    public static function pending(): array
    {
        return [self::OUVERTE, self::A_VERIFIER];
    }

    /**
     * Session status for a Hyperswitch payment status; null = no change
     * (the client hasn't paid yet, or the payment is still processing).
     */
    public static function fromProvider(?string $providerStatus): ?self
    {
        return match ($providerStatus) {
            // Only "succeeded" means paid; requires_capture / authorized are not
            'succeeded' => self::PAYEE,
            'failed' => self::ECHOUEE,
            'cancelled' => self::ANNULEE,
            'expired' => self::EXPIREE,
            default => null,
        };
    }

    /**
     * Hyperswitch statuses meaning "being processed, not final": wait and
     * check again (forced syncs with growing delays).
     */
    public static function providerIsProcessing(?string $providerStatus): bool
    {
        return in_array($providerStatus, ['processing', 'pending'], true);
    }

    public function isFinal(): bool
    {
        return ! in_array($this, self::pending(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::OUVERTE => 'Lien envoyé',
            self::A_VERIFIER => 'À vérifier',
            self::PAYEE => 'Payée',
            self::ECHOUEE => 'Échouée',
            self::ANNULEE => 'Annulée',
            self::EXPIREE => 'Expirée',
        };
    }
}
