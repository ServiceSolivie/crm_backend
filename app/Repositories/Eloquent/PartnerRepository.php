<?php

namespace App\Repositories\Eloquent;

use App\Filters\PartnerFilter;
use App\Models\Partner;
use App\Repositories\Contracts\PartnerRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PartnerRepository extends BaseRepository implements PartnerRepositoryInterface
{
    public function model(): string
    {
        return Partner::class;
    }

    public function paginateFiltered(PartnerFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()
            ->withCount('credentials')
            ->filter($filters)
            ->paginate($perPage);
    }
}
