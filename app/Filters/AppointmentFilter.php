<?php

namespace App\Filters;

/**
 * Supported query parameters:
 *
 *   GET /appointments?filter[status]=PLANIFIE
 *   GET /appointments?filter[agent_id]=7
 *   GET /appointments?filter[lead_id]=3
 *   GET /appointments?filter[from]=2026-06-01
 *   GET /appointments?filter[to]=2026-06-30
 *   GET /appointments?filter[team_id]=3
 *   GET /appointments?sort=-scheduled_at
 */
class AppointmentFilter extends QueryFilter
{
    protected function status(string $value): void
    {
        $this->builder->where('status', $value);
    }

    protected function agentId(string $value): void
    {
        $this->builder->where('agent_id', $value);
    }

    protected function leadId(string $value): void
    {
        $this->builder->where('lead_id', $value);
    }

    protected function teamId(string $value): void
    {
        $this->builder->whereHas('lead', fn ($query) => $query->where('team_id', $value));
    }

    protected function from(string $value): void
    {
        $this->builder->where('scheduled_at', '>=', $value);
    }

    protected function to(string $value): void
    {
        $this->builder->where('scheduled_at', '<=', $value);
    }

    /**
     * @return array<int, string>
     */
    protected function sortable(): array
    {
        return [
            'scheduled_at',
            'created_at',
            'status',
        ];
    }
}
