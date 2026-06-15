<?php

namespace App\Repositories\Eloquent;

use App\Filters\LeadImportFilter;
use App\Models\LeadImport;
use App\Repositories\Contracts\LeadImportRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LeadImportRepository extends BaseRepository implements LeadImportRepositoryInterface
{
    public function model(): string
    {
        return LeadImport::class;
    }

    public function paginateFiltered(LeadImportFilter $filters, int $perPage = 15, ?Closure $scope = null): LengthAwarePaginator
    {
        $query = $this->newQuery()->with('importedBy');

        if ($scope) {
            $scope($query);
        }

        return $query->filter($filters)->paginate($perPage);
    }
}
