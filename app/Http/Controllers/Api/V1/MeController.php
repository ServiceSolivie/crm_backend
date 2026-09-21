<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppointmentResource;
use App\Services\LeadWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Data about the signed-in user's own workload (sidebar counters, "Ma journée").
 */
class MeController extends Controller
{
    public function __construct(protected LeadWorkspaceService $workspace) {}

    /**
     * GET /me/counters
     */
    public function counters(Request $request): JsonResponse
    {
        return $this->success($this->workspace->counters($request->user()));
    }

    /**
     * GET /me/agenda?date=YYYY-MM-DD
     */
    public function agenda(Request $request): JsonResponse
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        $agenda = $this->workspace->agenda($request->user(), $data['date'] ?? null);

        return $this->success([
            ...$agenda,
            'overdue' => AppointmentResource::collection($agenda['overdue'])->resolve($request),
            'today' => AppointmentResource::collection($agenda['today'])->resolve($request),
        ]);
    }
}
