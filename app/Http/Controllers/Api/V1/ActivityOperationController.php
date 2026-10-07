<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ActivityCategoryEnum;
use App\Enums\ActivityEventEnum;
use App\Filters\ActivityOperationFilter;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityOperationResource;
use App\Models\ActivityOperation;
use App\Services\ActivityJournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Activity journal: the operations (a payment, a Google Ads submission, an
 * account's logins of the day…) and, for one operation, its logs in order.
 */
class ActivityOperationController extends Controller
{
    public function __construct(protected ActivityJournalService $journal) {}

    /**
     * GET /activity-operations (filters: ActivityOperationFilter)
     */
    public function index(Request $request, ActivityOperationFilter $filters): JsonResponse
    {
        $this->authorize('viewAny', ActivityOperation::class);

        $operations = $this->journal->paginate($filters, min(100, max(1, $request->integer('per_page', 20))));

        return $this->success(ActivityOperationResource::collection($operations));
    }

    /**
     * GET /activity-operations/summary — the counters of the category
     * chips for the current filters, and the list of events to filter on.
     */
    public function summary(ActivityOperationFilter $filters): JsonResponse
    {
        $this->authorize('viewAny', ActivityOperation::class);

        return $this->success([
            ...$this->journal->summary($filters),
            'events' => array_map(fn (ActivityEventEnum $event) => [
                'value' => $event->value,
                'label' => $event->label(),
                'category' => $event->category()->value,
            ], ActivityEventEnum::cases()),
            'category_labels' => array_column(ActivityCategoryEnum::options(), 'label', 'value'),
        ]);
    }

    /**
     * GET /activity-operations/{id} — one operation with all its logs.
     */
    public function show(int $activityOperation): JsonResponse
    {
        $this->authorize('viewAny', ActivityOperation::class);

        $operation = $this->journal->findWithLogs($activityOperation);
        if (! $operation) {
            return $this->error('Opération introuvable.', 404);
        }

        return $this->success(new ActivityOperationResource($operation));
    }
}
