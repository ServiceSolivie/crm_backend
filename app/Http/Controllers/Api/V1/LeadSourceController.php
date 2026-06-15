<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\LeadSourceFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeadSource\StoreLeadSourceRequest;
use App\Http\Requests\LeadSource\UpdateLeadSourceRequest;
use App\Http\Resources\LeadSourceResource;
use App\Models\LeadSource;
use App\Services\LeadSourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadSourceController extends Controller
{
    public function __construct(protected LeadSourceService $leadSourceService) {}

    public function index(Request $request, LeadSourceFilter $filters): JsonResponse
    {
        $this->authorize('viewAny', LeadSource::class);

        $perPage = (int) $request->integer('per_page', 15);

        $leadSources = $this->leadSourceService->paginateFiltered($filters, $perPage);

        return $this->success(LeadSourceResource::collection($leadSources));
    }

    public function store(StoreLeadSourceRequest $request): JsonResponse
    {
        $this->authorize('create', LeadSource::class);

        $leadSource = $this->leadSourceService->createLeadSource($request->validated());

        return $this->created(new LeadSourceResource($leadSource), 'Lead source created successfully');
    }

    public function show(LeadSource $leadSource): JsonResponse
    {
        $this->authorize('view', $leadSource);

        return $this->success(new LeadSourceResource($leadSource));
    }

    public function update(UpdateLeadSourceRequest $request, LeadSource $leadSource): JsonResponse
    {
        $this->authorize('update', $leadSource);

        $leadSource = $this->leadSourceService->updateLeadSource($leadSource, $request->validated());

        return $this->success(new LeadSourceResource($leadSource), 'Lead source updated successfully');
    }

    public function destroy(LeadSource $leadSource): JsonResponse
    {
        $this->authorize('delete', $leadSource);

        $this->leadSourceService->deleteLeadSource($leadSource);

        return $this->noContent('Lead source deleted successfully');
    }
}
