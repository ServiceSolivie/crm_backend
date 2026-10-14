<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

/**
 * How one logged event went.
 */
enum ActivityLevelEnum: string implements HasLabel
{
    use EnumHelpers;

    case SUCCESS = 'success';
    case INFO = 'info';
    case WARNING = 'warning';
    case FAILURE = 'failure';

    /**
     * Something a person should look at.
     */
    public function isProblem(): bool
    {
        return $this === self::WARNING || $this === self::FAILURE;
    }

    public function label(): string
    {
        return match ($this) {
            self::SUCCESS => 'Réussi',
            self::INFO => 'Information',
            self::WARNING => 'Attention',
            self::FAILURE => 'Échec',
        };
    }
}
