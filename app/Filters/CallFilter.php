<?php

namespace App\Filters;

/**
 * Supported query parameters:
 *
 *   GET /calls?filter[direction]=out
 *   GET /calls?filter[status]=missed
 *   GET /calls?filter[agent_id]=7
 *   GET /calls?filter[lead_id]=3
 *   GET /calls?filter[unmatched]=1          (calls not linked to any lead)
 *   GET /calls?filter[from]=2026-10-01
 *   GET /calls?filter[to]=2026-10-31
 *   GET /calls?sort=-started_at
 */
class CallFilter extends QueryFilter
{
    public function hasSort(): bool
    {
        return $this->request->filled('sort') || $this->request->filled('sort_by');
    }

    protected function direction(string $value): void
    {
        $this->builder->where('direction', $value);
    }

    protected function status(string $value): void
    {
        $this->builder->whereIn('status', explode(',', $value));
    }

    protected function agentId(string $value): void
    {
        $this->builder->where('user_id', $value);
    }

    protected function leadId(string $value): void
    {
        $this->builder->where('lead_id', $value);
    }

    protected function unmatched(string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder->whereNull('lead_id')->where('is_internal', false);
        }
    }

    protected function from(string $value): void
    {
        $this->builder->where('started_at', '>=', $value);
    }

    protected function to(string $value): void
    {
        $this->builder->where('started_at', '<=', $value.(strlen($value) === 10 ? ' 23:59:59' : ''));
    }

    /**
     * @return array<int, string>
     */
    protected function sortable(): array
    {
        return [
            'started_at',
            'duration_seconds',
            'talk_seconds',
            'status',
        ];
    }
}
