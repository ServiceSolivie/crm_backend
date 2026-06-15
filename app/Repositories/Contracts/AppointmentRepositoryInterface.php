<?php

namespace App\Repositories\Contracts;

use App\Filters\AppointmentFilter;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AppointmentRepositoryInterface extends RepositoryInterface
{
    /**
     * Paginate appointments, applying an optional visibility scope and the given filters/sorting.
     */
    public function paginateFiltered(AppointmentFilter $filters, int $perPage = 15, ?Closure $scope = null): LengthAwarePaginator;

    /**
     * Determine whether the given agent already has an active appointment at the given time.
     */
    public function hasConflict(int $agentId, string $scheduledAt, ?int $excludeId = null): bool;

    /**
     * Aggregate appointment counts (overall and by status), optionally scoped and date-bounded.
     *
     * @return array<string, mixed>
     */
    public function statistics(?Closure $scope = null, ?string $from = null, ?string $to = null): array;
}
