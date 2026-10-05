<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\CallFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Call\LinkRingoverCallRequest;
use App\Http\Requests\Call\UpdateCallRequest;
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

    /**
     * Start a call to a lead from the CRM. Returns the call and the numbers
     * the embedded Ringover phone must dial.
     */
    public function initiate(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);
        $this->authorize('create', Call::class);

        $call = $this->callService->initiate($lead, $request->user());

        return $this->created([
            'call' => new CallResource($call->load(['lead', 'agent'])),
            'dial' => [
                'to' => $call->to_number,
                'from' => $call->from_number,
            ],
        ], 'Appel initié');
    }

    public function linkRingover(LinkRingoverCallRequest $request, Call $call): JsonResponse
    {
        $this->authorize('update', $call);

        $call = $this->callService->linkRingoverCall($call, $request->validated('ringover_call_id'));

        return $this->success(new CallResource($call->load(['lead', 'agent'])));
    }

    public function update(UpdateCallRequest $request, Call $call): JsonResponse
    {
        $this->authorize('update', $call);

        $call = $this->callService->updateNote($call, $request->validated('note'));

        return $this->success(new CallResource($call->load(['lead', 'agent'])), 'Note enregistrée');
    }

    protected function perPage(Request $request): int
    {
        return min(100, max(1, $request->integer('per_page', 15)));
    }
}
