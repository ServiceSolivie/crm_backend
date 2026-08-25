<?php

namespace App\Services;

use App\Filters\PartnerFilter;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PartnerService extends BaseService
{
    public function __construct(protected PartnerRepositoryInterface $partners)
    {
        parent::__construct($partners);
    }

    public function paginateFiltered(PartnerFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->partners->paginateFiltered($filters, $perPage);
    }
}
