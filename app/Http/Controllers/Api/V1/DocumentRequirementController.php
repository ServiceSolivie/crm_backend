<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentRequirement\StoreDocumentTypeRequest;
use App\Http\Requests\DocumentRequirement\SyncRequirementsRequest;
use App\Http\Requests\DocumentRequirement\UpdateDocumentTypeRequest;
use App\Http\Resources\DocumentTypeResource;
use App\Models\DocumentType;
use App\Repositories\Contracts\DocumentTypeRepositoryInterface;
use App\Services\DocumentRequirementService;
use Illuminate\Http\JsonResponse;

class DocumentRequirementController extends Controller
{
    public function __construct(
        protected DocumentRequirementService $requirementService,
        protected DocumentTypeRepositoryInterface $documentTypeRepository,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', DocumentType::class);

        return $this->success($this->requirementService->getRequirementsMatrix());
    }

    public function documentTypes(): JsonResponse
    {
        $this->authorize('viewAny', DocumentType::class);

        $types = $this->documentTypeRepository->newQuery()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->success(DocumentTypeResource::collection($types));
    }

    public function storeDocumentType(StoreDocumentTypeRequest $request): JsonResponse
    {
        $this->authorize('manage', DocumentType::class);

        $type = $this->documentTypeRepository->create($request->validated());

        return $this->created(new DocumentTypeResource($type));
    }

    public function updateDocumentType(UpdateDocumentTypeRequest $request, DocumentType $documentType): JsonResponse
    {
        $this->authorize('manage', DocumentType::class);

        $type = $this->documentTypeRepository->update($documentType->id, $request->validated());

        return $this->success(new DocumentTypeResource($type));
    }

    public function deleteDocumentType(DocumentType $documentType): JsonResponse
    {
        $this->authorize('manage', DocumentType::class);

        $usedInDocuments = \App\Models\LeadDocument::where('document_type', $documentType->name)->exists();
        if ($usedInDocuments) {
            return $this->error('Ce type de document est utilisé dans des dossiers existants et ne peut pas être supprimé.', 409);
        }

        $this->documentTypeRepository->delete($documentType->id);

        return $this->noContent('Type de document supprimé avec succès');
    }

    public function syncRequirements(SyncRequirementsRequest $request): JsonResponse
    {
        $this->authorize('manage', DocumentType::class);

        $this->requirementService->syncRequirements(
            $request->validated('insurance_type'),
            $request->validated('client_type'),
            $request->validated('document_type_ids'),
        );

        return $this->success($this->requirementService->getRequirementsMatrix());
    }
}
