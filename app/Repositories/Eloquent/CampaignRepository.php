<?php

namespace App\Repositories\Eloquent;

use App\Filters\CampaignFilter;
use App\Models\Campaign;
use App\Repositories\Contracts\CampaignRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CampaignRepository extends BaseRepository implements CampaignRepositoryInterface
{
    public function model(): string
    {
        return Campaign::class;
    }

    public function paginateFiltered(CampaignFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()
            ->with('leadSource')
            ->withCount(['leads', 'touchpoints'])
            ->filter($filters)
            ->paginate($perPage);
    }
}
