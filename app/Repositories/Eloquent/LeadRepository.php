<?php

namespace App\Repositories\Eloquent;

use App\Filters\LeadFilter;
use App\Models\Lead;
use App\Repositories\Contracts\LeadRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class LeadRepository extends BaseRepository implements LeadRepositoryInterface
{
    public function model(): string
    {
        return Lead::class;
    }

    public function paginateFiltered(LeadFilter $filters, int $perPage = 15, ?Closure $scope = null): LengthAwarePaginator
    {
        $query = $this->newQuery()->with([
            'leadSource',
            'assignedAgent',
            'team',
            'creator',
        ]);

        if ($scope) {
            $scope($query);
        }
        Log::info('Lead filters', [
            'filters' => $filters,
        ]);
        return $query->filter($filters)->paginate($perPage);
    }

    public function referenceExists(string $reference): bool
    {
        return $this->model->newQuery()->where('reference', $reference)->exists();
    }
}
