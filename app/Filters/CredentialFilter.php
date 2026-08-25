<?php

namespace App\Filters;

class CredentialFilter extends QueryFilter
{
    protected function isActive(string $value): void
    {
        $this->builder->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    protected function partnerId(string $value): void
    {
        $this->builder->where('partner_id', $value);
    }

    protected function search(string $value): void
    {
        $this->builder->where(function ($query) use ($value) {
            $query->where('label', 'like', "%{$value}%")
                ->orWhereHas('partner', fn ($q) => $q->where('name', 'like', "%{$value}%"));
        });
    }

    /** @return array<int, string> */
    protected function sortable(): array
    {
        return ['label', 'created_at'];
    }
}
