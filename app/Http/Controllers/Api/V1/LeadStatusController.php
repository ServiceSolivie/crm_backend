<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeadStatus\StoreLeadStatusRequest;
use App\Http\Requests\LeadStatus\UpdateLeadStatusRequest;
use App\Http\Resources\LeadStatusResource;
use App\Models\LeadStatus;
use App\Services\LeadStatusService;
use Illuminate\Http\JsonResponse;

/**
 * Custom lead statuses. The system statuses are not served here: the
 * frontend already knows them (utils/enums.js).
 */
class LeadStatusController extends Controller
{
    public function __construct(protected LeadStatusService $leadStatusService) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', LeadStatus::class);

        return $this->success(LeadStatusResource::collection($this->leadStatusService->listCustom()));
    }

    public function store(StoreLeadStatusRequest $request): JsonResponse
    {
        $this->authorize('create', LeadStatus::class);

        $leadStatus = $this->leadStatusService->createLeadStatus($request->validated(), $request->user());

        return $this->created(new LeadStatusResource($leadStatus), 'Statut créé');
    }

    public function update(UpdateLeadStatusRequest $request, LeadStatus $leadStatus): JsonResponse
    {
        $this->authorize('update', $leadStatus);

        $leadStatus = $this->leadStatusService->updateLeadStatus($leadStatus, $request->validated());

        return $this->success(new LeadStatusResource($leadStatus), 'Statut mis à jour');
    }

    public function destroy(LeadStatus $leadStatus): JsonResponse
    {
        $this->authorize('delete', $leadStatus);

        $this->leadStatusService->deleteLeadStatus($leadStatus);

        return $this->noContent('Statut supprimé');
    }
}
