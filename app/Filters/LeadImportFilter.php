<?php

namespace App\Filters;

/**
 * Supported query parameters:
 *
 *   GET /lead-imports?filter[status]=completed
 *   GET /lead-imports?filter[imported_by]=3
 *   GET /lead-imports?sort=-created_at
 */
class LeadImportFilter extends QueryFilter
{
    protected function status(string $value): void
    {
        $this->builder->where('status', $value);
    }

    protected function importedBy(string $value): void
    {
        $this->builder->where('imported_by', $value);
    }

    /**
     * @return array<int, string>
     */
    protected function sortable(): array
    {
        return [
            'created_at',
            'status',
        ];
    }
}
