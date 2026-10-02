<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\CampaignFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Campaign\StoreCampaignRequest;
use App\Http\Requests\Campaign\UpdateCampaignRequest;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(protected CampaignService $campaigns) {}

    public function index(Request $request, CampaignFilter $filters): JsonResponse
    {
        $this->authorize('viewAny', Campaign::class);

        return $this->success(CampaignResource::collection($this->campaigns->paginate($filters, min(100, max(1, $request->integer('per_page', 15))))));
    }

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $this->authorize('create', Campaign::class);

        return $this->created(new CampaignResource($this->campaigns->create($request->validated())), 'Campaign created successfully.');
    }

    public function show(Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        return $this->success(new CampaignResource($campaign->load('leadSource')->loadCount(['leads', 'touchpoints'])));
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): JsonResponse
    {
        $this->authorize('update', $campaign);

        return $this->success(new CampaignResource($this->campaigns->update($campaign, $request->validated())), 'Campaign updated successfully.');
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        $this->authorize('delete', $campaign);
        $this->campaigns->delete($campaign);

        return $this->noContent('Campaign deleted successfully.');
    }
}
