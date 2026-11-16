<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * The leads page a lead shows on: « Leads » (general) holds every type not
 * placed on an extra page; an extra page (by slug) holds its own types.
 */
class LeadSection
{
    public const GENERAL = 'general';

    public static function apply(Builder $query, ?string $section, string $column = 'insurance_type'): Builder
    {
        if ($section === null || $section === '') {
            return $query;
        }

        $catalog = app(InsuranceTypeCatalog::class);

        if ($section === self::GENERAL) {
            $paged = $catalog->pagedCodes();

            return $paged
                ? $query->where(fn (Builder $q) => $q->whereNull($column)->orWhereNotIn($column, $paged))
                : $query;
        }

        // Unknown page → nothing rather than every lead
        return $query->whereIn($column, $catalog->codesForPage($section) ?: ['__none__']);
    }
}
