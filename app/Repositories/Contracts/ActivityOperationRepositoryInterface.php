<?php

namespace App\Repositories\Contracts;

use App\Filters\ActivityOperationFilter;
use App\Models\ActivityOperation;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ActivityOperationRepositoryInterface extends RepositoryInterface
{
    /**
     * The operations, most recent activity first.
     */
    public function paginateFiltered(ActivityOperationFilter $filters, int $perPage = 20): LengthAwarePaginator;

    /**
     * For these filters (whatever the category): how many operations, and
     * how many with a problem, in total and per category.
     *
     * @return array{total: int, problems: int, categories: array<string, array{count: int, problems: int}>}
     */
    public function summary(ActivityOperationFilter $filters): array;

    /**
     * One operation with all its logs, oldest first.
     */
    public function findWithLogs(int $id): ?ActivityOperation;

    /**
     * Delete the operations with no activity since $before (their logs go
     * with them).
     *
     * @return int number of operations deleted
     */
    public function pruneInactiveSince(CarbonInterface $before): int;
}
