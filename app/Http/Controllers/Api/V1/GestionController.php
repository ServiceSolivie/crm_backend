<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\FlagLeadIssueRequest;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Services\GestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GestionController extends Controller
{
    public function __construct(protected GestionService $gestionService) {}

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can(PermissionEnum::GESTION_REVIEW->value), 403, 'You do not have permission to view the gestion dashboard.');

        $stats = $this->gestionService->dashboardStats($user);
        $stats['stuck_leads'] = LeadResource::collection($stats['stuck_leads']);

        return $this->success($stats);
    }

    public function flagIssue(FlagLeadIssueRequest $request, Lead $lead): JsonResponse
    {
        $user = $request->user();

        abort_unless($user->can(PermissionEnum::GESTION_FLAG_DOCUMENTS->value), 403, 'You do not have permission to flag issues on leads.');

        $lead = $this->gestionService->flagIssue(
            $lead,
            $user,
            $request->validated('issue_types'),
            $request->validated('missing_documents'),
            $request->validated('comment'),
        );

        return $this->success(new LeadResource($lead), 'Le lead a été renvoyé à l\'agent pour correction');
    }
}
