<?php

namespace App\Filters;

class CampaignFilter extends QueryFilter
{
    protected function search(string $value): void
    {
        $this->builder->where(fn ($query) => $query->where('name', 'like', "%{$value}%")
            ->orWhere('external_campaign_id', 'like', "%{$value}%")
            ->orWhere('form_id', 'like', "%{$value}%"));
    }

    protected function isActive(string $value): void
    {
        $this->builder->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    protected function leadSourceId(string $value): void
    {
        $this->builder->where('lead_source_id', $value);
    }

    protected function sortable(): array
    {
        return ['name', 'external_campaign_id', 'form_id', 'created_at'];
    }
}
