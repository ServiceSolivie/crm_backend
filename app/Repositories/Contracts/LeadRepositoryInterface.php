<?php

namespace App\Repositories\Contracts;

use App\Filters\LeadFilter;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LeadRepositoryInterface extends RepositoryInterface
{
    /**
     * Paginate leads, applying an optional visibility scope and the given filters/sorting.
     */
    public function paginateFiltered(LeadFilter $filters, int $perPage = 15, ?Closure $scope = null): LengthAwarePaginator;

    /**
     * Determine whether a lead reference already exists.
     */
    public function referenceExists(string $reference): bool;
}
