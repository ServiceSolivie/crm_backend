<?php

namespace App\Services;

use App\Filters\ActivityOperationFilter;
use App\Models\ActivityOperation;
use App\Repositories\Contracts\ActivityOperationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Reading side of the activity journal (ActivityLogger writes it): the
 * operations, their counters per category, and the logs of one operation.
 */
class ActivityJournalService
{
    public function __construct(protected ActivityOperationRepositoryInterface $operations) {}

    public function paginate(ActivityOperationFilter $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->operations->paginateFiltered($filters, $perPage);
    }

    /**
     * @return array{total: int, problems: int, categories: array<string, array{count: int, problems: int}>}
     */
    public function summary(ActivityOperationFilter $filters): array
    {
        return $this->operations->summary($filters);
    }

    public function findWithLogs(int $id): ?ActivityOperation
    {
        return $this->operations->findWithLogs($id);
    }

    /**
     * Delete the operations with no activity for longer than the retention
     * (config activity_log.retention_days).
     *
     * @return int number of operations deleted
     */
    public function prune(?int $days = null): int
    {
        $days = max(1, $days ?? (int) config('activity_log.retention_days', 365));

        return $this->operations->pruneInactiveSince(now()->subDays($days));
    }
}
