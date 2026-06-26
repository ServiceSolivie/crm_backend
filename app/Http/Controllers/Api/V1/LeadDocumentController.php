<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ClientTypeEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Document\StoreLeadDocumentRequest;
use App\Http\Resources\DossierResource;
use App\Http\Resources\LeadDocumentResource;
use App\Http\Resources\LeadResource;
use App\Models\Lead;
use App\Models\LeadDocument;
use App\Services\LeadDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadDocumentController extends Controller
{
    public function __construct(protected LeadDocumentService $documentService) {}

    public function index(Lead $lead): JsonResponse
    {
        $this->authorize('viewAny', [LeadDocument::class, $lead]);

        $lead->load('documents.uploader');

        $status = $this->documentService->getDossierStatus($lead);

        return $this->success(new DossierResource($status));
    }

    public function store(StoreLeadDocumentRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('upload', [LeadDocument::class, $lead]);

        $document = $this->documentService->uploadDocument(
            $lead,
            $request->validated('document_type'),
            $request->file('file'),
            $request->user(),
        );

        return $this->created(new LeadDocumentResource($document->load('uploader')));
    }

    public function download(Lead $lead, LeadDocument $document): StreamedResponse
    {
        $this->authorize('download', $document);

        return $this->documentService->downloadDocument($document);
    }

    public function destroy(Lead $lead, LeadDocument $document): JsonResponse
    {
        $this->authorize('delete', $document);

        $this->documentService->deleteDocument($document);

        return $this->noContent('Document supprimé avec succès');
    }

    public function setClientType(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('upload', [LeadDocument::class, $lead]);

        $validated = $request->validate([
            'client_type' => ['required', Rule::in(ClientTypeEnum::values())],
        ]);

        $lead->update(['client_type' => $validated['client_type']]);

        return $this->success(new LeadResource($lead->refresh()->load(['leadSource', 'assignedAgent', 'team', 'creator'])));
    }
}
