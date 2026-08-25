<?php

namespace App\Filters;

class PartnerFilter extends QueryFilter
{
    protected function isActive(string $value): void
    {
        $this->builder->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    protected function search(string $value): void
    {
        $this->builder->where(function ($query) use ($value) {
            $query->where('name', 'like', "%{$value}%")
                ->orWhere('domain', 'like', "%{$value}%");
        });
    }

    /** @return array<int, string> */
    protected function sortable(): array
    {
        return ['name', 'domain', 'created_at'];
    }
}
