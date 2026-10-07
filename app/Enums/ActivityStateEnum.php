<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

/**
 * How an operation stands now (set by its most recent decisive log).
 */
enum ActivityStateEnum: string implements HasLabel
{
    use EnumHelpers;

    case SUCCESS = 'success';
    // Still going on: a link waiting for the client, a credit waiting for the bank
    case PENDING = 'pending';
    case WARNING = 'warning';
    case FAILURE = 'failure';
    // Closed without an outcome: cancelled, a test, a logout
    case NEUTRAL = 'neutral';

    public function isProblem(): bool
    {
        return $this === self::WARNING || $this === self::FAILURE;
    }

    public function label(): string
    {
        return match ($this) {
            self::SUCCESS => 'Réussi',
            self::PENDING => 'En cours',
            self::WARNING => 'À surveiller',
            self::FAILURE => 'Échec',
            self::NEUTRAL => 'Terminé',
        };
    }
}
