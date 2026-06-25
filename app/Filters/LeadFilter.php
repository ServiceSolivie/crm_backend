<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Supported query parameters (flat or nested under filter[...]):
 *
 *   GET /leads?status=VALIDE
 *   GET /leads?insurance_type=AUTO
 *   GET /leads?team_id=3
 *   GET /leads?assigned_to=7
 *   GET /leads?lead_source_id=2 (alias: source_id)
 *   GET /leads?city=Paris
 *   GET /leads?search=John                 (matches reference, name, phone, email)
 *   GET /leads?from=2026-06-01              (created_at >=)
 *   GET /leads?to=2026-06-30                (created_at <=)
 *   GET /leads?sort=-created_at,reference   or  ?sort_by=created_at&sort_dir=desc
 */
class LeadFilter extends QueryFilter
{
    protected function status(string $value): void
    {
        $this->builder->where('status', $value);
    }

    protected function insuranceType(string $value): void
    {
        $this->builder->where('insurance_type', $value);
    }

    protected function teamId(string $value): void
    {
        $this->builder->where('team_id', $value);
    }

    protected function assignedTo(string $value): void
    {
        $this->builder->where('assigned_to', $value);
    }

    protected function leadSourceId(string $value): void
    {
        $this->builder->where('lead_source_id', $value);
    }

    protected function sourceId(string $value): void
    {
        $this->builder->where('lead_source_id', $value);
    }

    protected function city(string $value): void
    {
        $this->builder->where('city', 'like', "%{$value}%");
    }

    protected function search(string $value): void
    {
        $this->builder->where(function (Builder $query) use ($value) {
            $query->where('reference', 'like', "%{$value}%")
                ->orWhere('first_name', 'like', "%{$value}%")
                ->orWhere('last_name', 'like', "%{$value}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$value}%"])
                ->orWhere('phone', 'like', "%{$value}%")
                ->orWhere('email', 'like', "%{$value}%");
        });
    }

    protected function from(string $value): void
    {
        $this->builder->where('created_at', '>=', $value);
    }

    protected function to(string $value): void
    {
        $this->builder->where('created_at', '<=', $value);
    }

    /**
     * @return array<int, string>
     */
    protected function sortable(): array
    {
        return [
            'created_at',
            'updated_at',
            'reference',
            'status',
            'first_name',
            'last_name',
        ];
    }
}
