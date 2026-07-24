<?php

namespace App\Repositories\Eloquent;

use App\Models\Contract;
use App\Repositories\Contracts\ContractRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ContractRepository extends BaseRepository implements ContractRepositoryInterface
{
    public function model(): string
    {
        return Contract::class;
    }

    public function paginateScoped(array $filters, int $perPage = 15, ?Closure $scope = null): LengthAwarePaginator
    {
        $query = $this->newQuery()
            ->with(['lead:id,reference,first_name,last_name', 'generator:id,name'])
            ->latest();

        if ($scope) {
            $scope($query);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('client_name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['template_key'])) {
            $query->where('template_key', $filters['template_key']);
        }

        if (! empty($filters['lead_id'])) {
            $query->where('lead_id', $filters['lead_id']);
        }

        return $query->paginate($perPage);
    }

    public function countForLeadAndTemplate(?int $leadId, string $templateKey): int
    {
        return $this->newQuery()
            ->where('template_key', $templateKey)
            ->when(
                $leadId !== null,
                fn ($query) => $query->where('lead_id', $leadId),
                fn ($query) => $query->whereNull('lead_id'),
            )
            ->count();
    }

    public function referenceExists(string $reference): bool
    {
        return $this->newQuery()->where('reference', $reference)->exists();
    }
}
