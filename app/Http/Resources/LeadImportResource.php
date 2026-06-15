<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class LeadImportResource extends BaseResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'file_name' => $this->file_name,
            'imported_by' => $this->whenLoaded('importedBy', fn () => [
                'id' => $this->importedBy->id,
                'name' => $this->importedBy->name,
            ]),
            'total_rows' => $this->total_rows,
            'success_rows' => $this->success_rows,
            'failed_rows' => $this->failed_rows,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'has_error_report' => $this->error_report_path !== null,
            'errors' => $this->when(isset($this->errors), fn () => $this->errors),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}
