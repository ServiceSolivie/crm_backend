<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\LeadImportFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeadImport\StoreLeadImportRequest;
use App\Http\Resources\LeadImportResource;
use App\Models\LeadImport;
use App\Services\LeadImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadImportController extends Controller
{
    public function __construct(protected LeadImportService $leadImportService) {}

    public function index(Request $request, LeadImportFilter $filters): JsonResponse
    {
        $this->authorize('viewAny', LeadImport::class);

        $perPage = (int) $request->integer('per_page', 15);

        $imports = $this->leadImportService->paginateForUser($request->user(), $filters, $perPage);

        return $this->success(LeadImportResource::collection($imports));
    }

    public function store(StoreLeadImportRequest $request): JsonResponse
    {
        $this->authorize('create', LeadImport::class);

        $import = $this->leadImportService->import($request->file('file'), $request->user());

        $import->load('importedBy');
        $import->errors = $this->leadImportService->errors($import);

        return $this->created(new LeadImportResource($import), 'Leads imported');
    }

    public function show(LeadImport $leadImport): JsonResponse
    {
        $this->authorize('view', $leadImport);

        $leadImport->load('importedBy');
        $leadImport->errors = $this->leadImportService->errors($leadImport);

        return $this->success(new LeadImportResource($leadImport));
    }
}
