<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\CallFilter;
use App\Http\Controllers\Controller;
use App\Http\Resources\CallResource;
use App\Models\Call;
use App\Models\Lead;
use App\Services\CallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallController extends Controller
{
    public function __construct(protected CallService $callService) {}

    public function index(Request $request, CallFilter $filters): JsonResponse
    {
        $this->authorize('viewAny', Call::class);

        $calls = $this->callService->paginateForUser($request->user(), $filters, $this->perPage($request));

        return $this->success(CallResource::collection($calls));
    }

    public function forLead(Request $request, Lead $lead, CallFilter $filters): JsonResponse
    {
        $this->authorize('view', $lead);
        $this->authorize('viewAny', Call::class);

        $calls = $this->callService->paginateForLead($lead, $request->user(), $filters, $this->perPage($request));

        return $this->success(CallResource::collection($calls));
    }

    public function show(Call $call): JsonResponse
    {
        $call->load(['lead', 'agent']);

        $this->authorize('view', $call);

        return $this->success(new CallResource($call));
    }

    protected function perPage(Request $request): int
    {
        return min(100, max(1, $request->integer('per_page', 15)));
    }
}
