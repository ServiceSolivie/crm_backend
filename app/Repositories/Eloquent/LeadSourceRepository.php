<?php

namespace App\Repositories\Eloquent;

use App\Filters\LeadSourceFilter;
use App\Models\LeadSource;
use App\Repositories\Contracts\LeadSourceRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LeadSourceRepository extends BaseRepository implements LeadSourceRepositoryInterface
{
    public function model(): string
    {
        return LeadSource::class;
    }

    public function paginateFiltered(LeadSourceFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()->filter($filters)->paginate($perPage);
    }

    public function leadsCount(int $leadSourceId): int
    {
        return $this->model->newQuery()->findOrFail($leadSourceId)->leads()->count();
    }
}
