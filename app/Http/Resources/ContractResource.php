<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ContractResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'template_key' => $this->template_key,
            'version' => $this->version,
            'client_name' => $this->client_name,
            'form_data' => $this->data,
            'original_filename' => $this->original_filename,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'lead' => $this->whenLoaded('lead', fn () => $this->lead ? [
                'id' => $this->lead->id,
                'reference' => $this->lead->reference,
                'name' => trim("{$this->lead->first_name} {$this->lead->last_name}"),
            ] : null),
            'generated_by' => $this->whenLoaded('generator', fn () => [
                'id' => $this->generator->id,
                'name' => $this->generator->name,
            ]),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }
}
