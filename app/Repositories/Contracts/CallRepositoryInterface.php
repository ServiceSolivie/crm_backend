<?php

namespace App\Repositories\Contracts;

use App\Filters\CallFilter;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CallRepositoryInterface extends RepositoryInterface
{
    /**
     * Paginate calls (newest first by default), applying a visibility scope and the given filters.
     */
    public function paginateFiltered(CallFilter $filters, int $perPage = 15, ?Closure $scope = null): LengthAwarePaginator;
}
