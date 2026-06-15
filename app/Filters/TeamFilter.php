<?php

namespace App\Filters;

/**
 * Supported query parameters:
 *
 *   GET /teams?filter[is_active]=1
 *   GET /teams?filter[manager_id]=3
 *   GET /teams?filter[search]=Sales        (matches name)
 *   GET /teams?sort=-created_at,name
 */
class TeamFilter extends QueryFilter
{
    protected function isActive(string $value): void
    {
        $this->builder->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    protected function managerId(string $value): void
    {
        $this->builder->where('manager_id', $value);
    }

    protected function search(string $value): void
    {
        $this->builder->where('name', 'like', "%{$value}%");
    }

    /**
     * @return array<int, string>
     */
    protected function sortable(): array
    {
        return [
            'created_at',
            'updated_at',
            'name',
        ];
    }
}
