<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardFilterRequest;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function kpis(DashboardFilterRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->dashboardService->canView($user), 403, 'You do not have permission to view the dashboard.');

        return $this->success($this->dashboardService->kpis(
            $user,
            $request->validated('from'),
            $request->validated('to'),
        ));
    }

    public function statistics(DashboardFilterRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->dashboardService->canView($user), 403, 'You do not have permission to view the dashboard.');

        return $this->success($this->dashboardService->statistics(
            $user,
            $request->validated('from'),
            $request->validated('to'),
        ));
    }

    public function aggregations(DashboardFilterRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->dashboardService->canView($user), 403, 'You do not have permission to view the dashboard.');

        return $this->success($this->dashboardService->aggregations(
            $user,
            $request->validated('from'),
            $request->validated('to'),
        ));
    }

    public function charts(DashboardFilterRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->dashboardService->canView($user), 403, 'You do not have permission to view the dashboard.');

        $days = (int) ($request->validated('days') ?? 14);

        return $this->success($this->dashboardService->charts($user, $days));
    }
}
