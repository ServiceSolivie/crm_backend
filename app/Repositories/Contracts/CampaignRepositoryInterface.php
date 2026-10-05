<?php

namespace App\Repositories\Contracts;

use App\Filters\CampaignFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CampaignRepositoryInterface extends RepositoryInterface
{
    public function paginateFiltered(CampaignFilter $filters, int $perPage = 15): LengthAwarePaginator;
}
