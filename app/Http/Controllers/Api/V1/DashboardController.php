<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\DashboardFilterRequest;
use App\Services\DashboardService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(protected DashboardService $dashboardService) {}

    public function kpis(DashboardFilterRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->dashboardService->canView($user), 403, 'You do not have permission to view the dashboard.');

        [$from, $to] = $this->resolveDates($request);
        [$teamId, $agentId] = $this->resolveFilters($request);

        return $this->success($this->dashboardService->kpis($user, $from, $to, $teamId, $agentId));
    }

    public function statistics(DashboardFilterRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($this->dashboardService->canView($user), 403, 'You do not have permission to view the dashboard.');

        [$from, $to] = $this->resolveDates($request);
        [$teamId, $agentId] = $this->resolveFilters($request);

        return $this->success($this->dashboardService->statistics($user, $from, $to, $teamId, $agentId));
    }

    public function aggregations(DashboardFilterRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->dashboardService->canView($user), 403, 'You do not have permission to view the dashboard.');

        [$from, $to] = $this->resolveDates($request);
        [$teamId, $agentId] = $this->resolveFilters($request);

        return $this->success($this->dashboardService->aggregations($user, $from, $to, $teamId, $agentId));
    }

    public function charts(DashboardFilterRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->dashboardService->canView($user), 403, 'You do not have permission to view the dashboard.');

        $days = (int) ($request->validated('days') ?? 14);
        [$teamId, $agentId] = $this->resolveFilters($request);

        return $this->success($this->dashboardService->charts($user, $days, $teamId, $agentId));
    }

    public function revenue(DashboardFilterRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->dashboardService->canViewRevenue($user), 403, 'Vous n\'avez pas la permission de consulter le chiffre d\'affaires.');

        [$from, $to] = $this->resolveDates($request);
        [$teamId, $agentId] = $this->resolveFilters($request);

        return $this->success($this->dashboardService->revenue($user, $from, $to, $teamId, $agentId));
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function resolveFilters(DashboardFilterRequest $request): array
    {
        $teamId = $request->validated('team_id');
        $agentId = $request->validated('agent_id');

        return [$teamId ? (int) $teamId : null, $agentId ? (int) $agentId : null];
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveDates(DashboardFilterRequest $request): array
    {
        $from = $request->validated('from');
        $to = $request->validated('to');

        if ($from || $to) {
            return [$from, $to];
        }

        $days = $request->validated('days');

        if ($days) {
            return [
                Carbon::today()->subDays((int) $days - 1)->toDateString(),
                Carbon::today()->toDateString(),
            ];
        }

        return [null, null];
    }
}
