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
     * Ringover sends "inbound"/"outbound" (webhooks) or "IN"/"OUT" (calls API).
     */
    public static function fromRingover(?string $value): ?self
    {
        return match (strtolower((string) $value)) {
            'in', 'inbound' => self::IN,
            'out', 'outbound' => self::OUT,
            default => null,
        };
    }
}
