<?php

namespace App\Repositories\Contracts;

use App\Filters\CredentialFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CredentialRepositoryInterface extends RepositoryInterface
{
    public function paginateFiltered(CredentialFilter $filters, int $perPage = 15): LengthAwarePaginator;
}
