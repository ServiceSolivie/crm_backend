<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DossierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $documents = collect($this->resource['documents'])->map(fn (array $item) => [
            'type' => $item['type'],
            'type_label' => $item['type_label'],
            'status' => $item['status'],
            'document' => $item['document'] ? new LeadDocumentResource($item['document']) : null,
        ]);

        return [
            'requires_client_type' => $this->resource['requires_client_type'],
            'completion' => $this->resource['completion'],
            'total_required' => $this->resource['total_required'],
            'total_uploaded' => $this->resource['total_uploaded'],
            'total_missing' => $this->resource['total_missing'],
            'documents' => $documents,
        ];
    }
}
