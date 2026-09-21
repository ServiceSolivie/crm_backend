<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * Supported query parameters (flat or nested under filter[...]):
 *
 *   GET /appointments?filter[status]=PLANIFIE    (several: status[]=… or status=PLANIFIE,CONFIRME)
 *   GET /appointments?filter[agent_id]=7         (accepts several values too)
 *   GET /appointments?filter[lead_id]=3
 *   GET /appointments?filter[from]=2026-06-01
 *   GET /appointments?filter[to]=2026-06-30
 *   GET /appointments?filter[team_id]=3
 *   GET /appointments?search=Dupont             (lead name, phone, e-mail or reference)
 *   GET /appointments?sort=-scheduled_at
 */
class AppointmentFilter extends QueryFilter
{
    protected function status(string|array $value): void
    {
        $this->whereIn('status', $value);
    }

    protected function agentId(string|array $value): void
    {
        $this->whereIn('agent_id', $value);
    }

    protected function leadId(string $value): void
    {
        $this->builder->where('lead_id', $value);
    }

    protected function teamId(string $value): void
    {
        $this->builder->whereHas('lead', fn ($query) => $query->where('team_id', $value));
    }

    protected function search(string $value): void
    {
        $this->builder->whereHas('lead', function (Builder $query) use ($value) {
            $query->where(fn (Builder $q) => $q
                ->where('reference', 'like', "%{$value}%")
                ->orWhere('first_name', 'like', "%{$value}%")
                ->orWhere('last_name', 'like', "%{$value}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$value}%"])
                ->orWhere('phone', 'like', "%{$value}%")
                ->orWhere('email', 'like', "%{$value}%"));
        });
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
