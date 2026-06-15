<?php

namespace App\Repositories\Contracts;

use App\Filters\UserFilter;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface extends RepositoryInterface
{
    public function paginateFiltered(UserFilter $filters, int $perPage = 15, ?Closure $scope = null): LengthAwarePaginator;
}
