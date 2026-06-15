<?php

namespace App\Enums\Concerns;

/**
 * Shared helpers for backed enums.
 *
 * If the enum also implements App\Enums\Contracts\HasLabel,
 * options() will include the label for each case.
 */
trait EnumHelpers
{
    /**
     * @return array<int, int|string>
     */
    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_map(fn ($case) => $case->name, self::cases());
    }

    /**
     * @return array<int, array{value: int|string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => method_exists($case, 'label') ? $case->label() : $case->name,
        ], self::cases());
    }

    public static function fromValue(int|string $value): ?self
    {
        return self::tryFrom($value);
    }
}
