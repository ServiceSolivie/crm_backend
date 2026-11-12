<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Limits queries to the lead sources a user is allowed to see.
 * A null list means no limit.
 */
class LeadSourceScope
{
    /**
     * @param  list<int>|null  $sourceIds
     */
    public static function leads(Builder $query, ?array $sourceIds, string $column = 'lead_source_id'): Builder
    {
        return $sourceIds === null ? $query : $query->whereIn($column, $sourceIds);
    }

    /**
     * For records linked to a lead: appointments, calls, payments.
     *
     * @param  list<int>|null  $sourceIds
     */
    public static function viaLead(Builder $query, ?array $sourceIds): Builder
    {
        return $sourceIds === null
            ? $query
            : $query->whereHas('lead', fn (Builder $lead) => $lead->whereIn('lead_source_id', $sourceIds));
    }
}
