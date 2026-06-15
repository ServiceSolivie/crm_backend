<?php

namespace App\Repositories\Contracts;

use App\Filters\LeadSourceFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LeadSourceRepositoryInterface extends RepositoryInterface
{
    public function paginateFiltered(LeadSourceFilter $filters, int $perPage = 15): LengthAwarePaginator;

    public function leadsCount(int $leadSourceId): int;
}
