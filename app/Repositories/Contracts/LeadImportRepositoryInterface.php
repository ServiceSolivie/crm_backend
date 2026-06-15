<?php

namespace App\Repositories\Contracts;

use App\Filters\LeadImportFilter;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LeadImportRepositoryInterface extends RepositoryInterface
{
    public function paginateFiltered(LeadImportFilter $filters, int $perPage = 15, ?Closure $scope = null): LengthAwarePaginator;
}
