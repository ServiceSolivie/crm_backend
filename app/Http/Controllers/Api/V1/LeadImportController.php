<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LeadImportStatusEnum;
use App\Enums\RoleEnum;
use App\Filters\LeadImportFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\LeadImport\StoreLeadImportRequest;
use App\Http\Resources\LeadImportResource;
use App\Models\LeadImport;
use App\Services\GoogleSheetLeadImporter;
use App\Services\LeadImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeadImportController extends Controller
{
    public function __construct(
        protected LeadImportService $leadImportService,
        protected GoogleSheetLeadImporter $googleSheetLeadImporter,
    ) {}

    public function index(Request $request, LeadImportFilter $filters): JsonResponse
    {
        $this->authorize('viewAny', LeadImport::class);

        $perPage = (int) $request->integer('per_page', 15);

        $imports = $this->leadImportService->paginateForUser($request->user(), $filters, $perPage);

        return $this->success(LeadImportResource::collection($imports));
    }

    public function store(StoreLeadImportRequest $request): JsonResponse
    {
        Log::info('Importing leads');
        $this->authorize('create', LeadImport::class);

        $import = $this->leadImportService->import($request->file('file'), $request->user());

        $import->load('importedBy');
        $import->errors = $this->leadImportService->errors($import);
        Log::info('errors: ' . json_encode($import->errors));
        return $this->created(new LeadImportResource($import), 'Leads imported');
    }

    public function show(LeadImport $leadImport): JsonResponse
    {
        $this->authorize('view', $leadImport);

        $leadImport->load('importedBy');
        $leadImport->errors = $this->leadImportService->errors($leadImport);

        return $this->success(new LeadImportResource($leadImport));
    }

    /**
     * One-time bulk seed from the sheets' current state — super_admin only.
     * Not gated via a permission/policy like the CSV import above, since
     * this is meant to be an occasional, deliberate baseline-setting action
     * rather than a routine one; a direct role check matches how other
     * super-admin-only actions in this app are already gated (e.g.
     * LeadSourcePolicy, TeamPolicy).
     */
    public function syncFromGoogleSheets(Request $request): JsonResponse
    {
        if (! $request->user()->hasRole(RoleEnum::SUPER_ADMIN->value)) {
            abort(403);
        }

        $results = [];

        foreach (['Lead', 'Decennale', 'Detailles'] as $sheet) {
            $stats = $this->googleSheetLeadImporter->seedFromSheet($sheet);

            LeadImport::create([
                'file_name' => "Google Sheets: {$sheet}",
                'imported_by' => $request->user()->id,
                'total_rows' => $stats['total'],
                'success_rows' => $stats['imported'],
                'failed_rows' => $stats['failed'],
                'status' => LeadImportStatusEnum::COMPLETED->value,
            ]);

            $results[$sheet] = $stats;
        }

        return $this->success($results, 'Sheets synced');
    }
}
