<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeadPage\SaveLeadPageRequest;
use App\Http\Resources\LeadPageResource;
use App\Models\LeadPage;
use App\Services\InsuranceTypeService;
use Illuminate\Http\JsonResponse;

/**
 * Extra leads pages (Décennale, Flotte & Camion…) shown in the menu.
 */
class LeadPageController extends Controller
{
    public function __construct(protected InsuranceTypeService $service) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', LeadPage::class);

        return $this->success(LeadPageResource::collection($this->service->listPages()));
    }

    public function store(SaveLeadPageRequest $request): JsonResponse
    {
        $this->authorize('create', LeadPage::class);

        return $this->created(new LeadPageResource($this->service->createPage($request->validated())), 'Page créée');
    }

    public function update(SaveLeadPageRequest $request, LeadPage $leadPage): JsonResponse
    {
        $this->authorize('update', $leadPage);

        return $this->success(new LeadPageResource($this->service->updatePage($leadPage, $request->validated())), 'Page mise à jour');
    }

    public function destroy(LeadPage $leadPage): JsonResponse
    {
        $this->authorize('delete', $leadPage);

        $this->service->deletePage($leadPage);

        return $this->noContent('Page supprimée');
    }
}
