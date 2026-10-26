<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;
use App\Enums\Contracts\HasLabel;

enum CallDirectionEnum: string implements HasLabel
{
    use EnumHelpers;

    case IN = 'in';
    case OUT = 'out';

    public function label(): string
    {
        return match ($this) {
            self::IN => 'Entrant',
            self::OUT => 'Sortant',
        };
    }

    /**
     * Ringover sends "in"/"out" (webhooks) or "IN"/"OUT" (calls API).
     */
    public static function fromRingover(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom(strtolower($value));
    }
}
