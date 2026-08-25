<?php

namespace App\Repositories\Contracts;

use App\Filters\PartnerFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PartnerRepositoryInterface extends RepositoryInterface
{
    public function paginateFiltered(PartnerFilter $filters, int $perPage = 15): LengthAwarePaginator;
}
