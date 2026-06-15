<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\AppointmentFilter;
use App\Filters\LeadFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\AgentReportRequest;
use App\Http\Requests\Report\ConversionReportRequest;
use App\Http\Requests\Report\TeamReportRequest;
use App\Http\Resources\AgentReportResource;
use App\Http\Resources\AppointmentResource;
use App\Http\Resources\ConversionReportResource;
use App\Http\Resources\LeadResource;
use App\Http\Resources\TeamReportResource;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    public function leads(Request $request, LeadFilter $filters): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->reportService->canView($user), 403, 'You do not have permission to view reports.');

        $perPage = (int) $request->integer('per_page', 15);

        $leads = $this->reportService->leadReport($user, $filters, $perPage);

        return $this->success(LeadResource::collection($leads));
    }

    public function appointments(Request $request, AppointmentFilter $filters): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->reportService->canView($user), 403, 'You do not have permission to view reports.');

        $perPage = (int) $request->integer('per_page', 15);

        $appointments = $this->reportService->appointmentReport($user, $filters, $perPage);

        return $this->success(AppointmentResource::collection($appointments));
    }

    public function teams(TeamReportRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->reportService->canView($user), 403, 'You do not have permission to view reports.');

        $perPage = (int) ($request->validated('per_page') ?? 15);

        $teams = $this->reportService->teamReport(
            $user,
            $request->validated('from'),
            $request->validated('to'),
            $perPage,
        );

        return $this->success(TeamReportResource::collection($teams));
    }

    public function agents(AgentReportRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->reportService->canView($user), 403, 'You do not have permission to view reports.');

        $perPage = (int) ($request->validated('per_page') ?? 15);

        $teamId = $request->validated('team_id');

        $agents = $this->reportService->agentReport(
            $user,
            $teamId !== null ? (int) $teamId : null,
            $request->validated('from'),
            $request->validated('to'),
            $perPage,
        );

        return $this->success(AgentReportResource::collection($agents));
    }

    public function conversion(ConversionReportRequest $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($this->reportService->canView($user), 403, 'You do not have permission to view reports.');

        $perPage = (int) ($request->validated('per_page') ?? 15);
        $groupBy = $request->validated('group_by') ?? 'source';

        $conversion = $this->reportService->conversionReport(
            $user,
            $groupBy,
            $request->validated('from'),
            $request->validated('to'),
            $perPage,
        );

        return $this->success(ConversionReportResource::collection($conversion));
    }
}
