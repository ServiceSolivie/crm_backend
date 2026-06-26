<?php

namespace App\Services;

use App\Enums\DocumentTypeEnum;
use App\Models\Lead;
use App\Models\LeadDocument;
use App\Models\User;
use App\Repositories\Contracts\LeadDocumentRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadDocumentService
{
    public function __construct(protected LeadDocumentRepositoryInterface $repository) {}

    /**
     * Get the full dossier status for a lead: required docs, uploaded docs, missing docs, completion %.
     */
    public function getDossierStatus(Lead $lead): array
    {
        $required = DossierRequirementsConfig::getRequiredDocuments(
            $lead->insurance_type,
            $lead->client_type,
        );

        if ($required === null) {
            return [
                'requires_client_type' => true,
                'completion' => 0,
                'total_required' => 0,
                'total_uploaded' => 0,
                'total_missing' => 0,
                'documents' => [],
            ];
        }

        $uploaded = $lead->documents->keyBy(fn (LeadDocument $doc) => $doc->document_type->value);

        $documents = [];
        foreach ($required as $docType) {
            $doc = $uploaded->get($docType->value);
            $documents[] = [
                'type' => $docType->value,
                'type_label' => $docType->label(),
                'status' => $doc ? 'uploaded' : 'missing',
                'document' => $doc,
            ];
        }

        $totalRequired = count($required);
        $totalUploaded = count(array_filter($documents, fn ($d) => $d['status'] === 'uploaded'));

        return [
            'requires_client_type' => false,
            'completion' => $totalRequired > 0 ? round(($totalUploaded / $totalRequired) * 100) : 100,
            'total_required' => $totalRequired,
            'total_uploaded' => $totalUploaded,
            'total_missing' => $totalRequired - $totalUploaded,
            'documents' => $documents,
        ];
    }

    public function uploadDocument(Lead $lead, DocumentTypeEnum $type, UploadedFile $file, User $uploader): LeadDocument
    {
        $required = DossierRequirementsConfig::getRequiredDocuments($lead->insurance_type, $lead->client_type);

        if ($required === null || ! in_array($type, $required)) {
            throw ValidationException::withMessages([
                'document_type' => 'Ce type de document n\'est pas requis pour ce lead.',
            ]);
        }

        $existing = $this->repository->findByLeadAndType($lead->id, $type);
        if ($existing) {
            Storage::disk('local')->delete($existing->file_path);
            $this->repository->delete($existing->id);
        }

        $path = $file->store("lead_documents/{$lead->id}", 'local');

        /** @var LeadDocument $document */
        $document = $this->repository->create([
            'lead_id' => $lead->id,
            'document_type' => $type->value,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => $uploader->id,
        ]);

        return $document;
    }

    public function deleteDocument(LeadDocument $document): void
    {
        Storage::disk('local')->delete($document->file_path);
        $this->repository->delete($document->id);
    }

    public function downloadDocument(LeadDocument $document): StreamedResponse
    {
        return Storage::disk('local')->download(
            $document->file_path,
            $document->original_filename,
        );
    }
}
