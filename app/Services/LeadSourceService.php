<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Filters\LeadSourceFilter;
use App\Models\LeadSource;
use App\Repositories\Contracts\LeadSourceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LeadSourceService
{
    public function __construct(protected LeadSourceRepositoryInterface $leadSources) {}

    public function paginateFiltered(LeadSourceFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->leadSources->paginateFiltered($filters, $perPage);
    }

    public function createLeadSource(array $attributes): LeadSource
    {
        $attributes['is_active'] = $attributes['is_active'] ?? true;

        return $this->leadSources->create($attributes);
    }

    public function updateLeadSource(LeadSource $leadSource, array $attributes): LeadSource
    {
        $leadSource->update($attributes);

        return $leadSource->refresh();
    }

    public function deleteLeadSource(LeadSource $leadSource): bool
    {
        if ($this->leadSources->leadsCount($leadSource->id) > 0) {
            throw new ApiException('Cannot delete a lead source that is still referenced by leads.', 409);
        }

        return (bool) $leadSource->delete();
    }
}
