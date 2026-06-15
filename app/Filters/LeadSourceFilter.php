<?php

namespace App\Filters;

/**
 * Supported query parameters:
 *
 *   GET /lead-sources?filter[is_active]=1
 *   GET /lead-sources?filter[search]=Facebook   (matches name or code)
 *   GET /lead-sources?sort=name
 */
class LeadSourceFilter extends QueryFilter
{
    protected function isActive(string $value): void
    {
        $this->builder->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    protected function search(string $value): void
    {
        $this->builder->where(function ($query) use ($value) {
            $query->where('name', 'like', "%{$value}%")
                ->orWhere('code', 'like', "%{$value}%");
        });
    }

    /**
     * @return array<int, string>
     */
    protected function sortable(): array
    {
        return [
            'name',
            'code',
            'created_at',
        ];
    }
}
