<?php

namespace App\Repositories\Eloquent;

use App\Enums\ActivityCategoryEnum;
use App\Enums\ActivityStateEnum;
use App\Filters\ActivityOperationFilter;
use App\Models\ActivityOperation;
use App\Repositories\Contracts\ActivityOperationRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ActivityOperationRepository extends BaseRepository implements ActivityOperationRepositoryInterface
{
    public function model(): string
    {
        return ActivityOperation::class;
    }

    public function paginateFiltered(ActivityOperationFilter $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->newQuery()
            ->with('lead:id,reference,first_name,last_name')
            ->filter($filters)
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    public function summary(ActivityOperationFilter $filters): array
    {
        // The chips show every category, so the category filter is left out
        $problem = "(problems_count > 0 OR state IN ('".ActivityStateEnum::WARNING->value."', '".ActivityStateEnum::FAILURE->value."'))";

        $rows = $this->newQuery()
            ->filter($filters->without(['category']))
            ->reorder()
            ->selectRaw('category')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN {$problem} THEN 1 ELSE 0 END) AS problems")
            ->selectRaw('SUM(CASE WHEN has_refund = 1 THEN 1 ELSE 0 END) AS refunds')
            ->selectRaw("SUM(CASE WHEN has_refund = 1 AND {$problem} THEN 1 ELSE 0 END) AS refund_problems")
            ->groupBy('category')
            ->toBase()
            ->get();

        $categories = [];
        foreach (ActivityCategoryEnum::cases() as $category) {
            $categories[$category->value] = ['count' => 0, 'problems' => 0];
        }

        $total = 0;
        $problems = 0;
        foreach ($rows as $row) {
            $total += (int) $row->total;
            $problems += (int) $row->problems;

            if (isset($categories[$row->category])) {
                $categories[$row->category] = ['count' => (int) $row->total, 'problems' => (int) $row->problems];
            }

            // Refunds live inside payment operations
            $categories[ActivityCategoryEnum::REFUND->value]['count'] += (int) $row->refunds;
            $categories[ActivityCategoryEnum::REFUND->value]['problems'] += (int) $row->refund_problems;
        }

        return ['total' => $total, 'problems' => $problems, 'categories' => $categories];
    }

    public function findWithLogs(int $id): ?ActivityOperation
    {
        /** @var ActivityOperation|null $operation */
        $operation = $this->newQuery()
            ->with(['lead:id,reference,first_name,last_name', 'logs.actor:id,name'])
            ->find($id);

        return $operation;
    }

    public function pruneInactiveSince(CarbonInterface $before): int
    {
        $deleted = 0;

        do {
            $ids = $this->newQuery()
                ->whereRaw('COALESCE(last_activity_at, created_at) < ?', [$before])
                ->limit(500)
                ->pluck('id');

            $deleted += $ids->isEmpty() ? 0 : $this->newQuery()->whereIn('id', $ids)->delete();
        } while ($ids->count() === 500);

        return $deleted;
    }
}
