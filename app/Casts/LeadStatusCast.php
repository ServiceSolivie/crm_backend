<?php

namespace App\Casts;

use App\Enums\LeadStatusEnum;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\SerializesCastableAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A lead status column holds either a system status or a custom one.
 *
 * Reading gives the LeadStatusEnum case, or null for a custom status: every
 * rule written against a named case (=== LeadStatusEnum::VALIDE, the closed
 * statuses, the gestion counters…) is therefore simply false for a custom
 * status. The stored code itself is read through the model's *_code accessor.
 */
class LeadStatusCast implements CastsAttributes, SerializesCastableAttributes
{
    public bool $withoutObjectCaching = true;

    public function get(Model $model, string $key, mixed $value, array $attributes): ?LeadStatusEnum
    {
        return $value === null ? null : LeadStatusEnum::tryFrom((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value instanceof LeadStatusEnum) {
            return $value->value;
        }

        return $value === null ? null : (string) $value;
    }

    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $attributes[$key] ?? null;
    }
}
