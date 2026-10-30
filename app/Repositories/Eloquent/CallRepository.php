<?php

namespace App\Repositories\Eloquent;

use App\Filters\CallFilter;
use App\Models\Call;
use App\Repositories\Contracts\CallRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CallRepository extends BaseRepository implements CallRepositoryInterface
{
    public function model(): string
    {
        return Call::class;
    }

    public function paginateFiltered(CallFilter $filters, int $perPage = 15, ?Closure $scope = null): LengthAwarePaginator
    {
        $query = $this->newQuery()->with(['lead', 'agent']);

        if ($scope) {
            $scope($query);
        }

        $query->filter($filters);

        if (! $filters->hasSort()) {
            $query->orderByDesc('started_at')->orderByDesc('id');
        }

        return $query->paginate($perPage);
    }
}
