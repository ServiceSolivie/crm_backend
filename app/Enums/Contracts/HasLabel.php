<?php

namespace App\Enums\Contracts;

/**
 * Implemented by enums that need a human-readable label,
 * e.g. for select options or badges.
 */
interface HasLabel
{
    public function label(): string;
}
