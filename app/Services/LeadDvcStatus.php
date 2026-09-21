<?php

namespace App\Services;

use App\Enums\DocumentTypeEnum;
use App\Enums\DvcStatusEnum;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\LeadDocument;

/**
 * Keeps leads.dvc_status in line with what the lead has:
 *   signed copy uploaded ("DVC" document) → SIGNE
 *   a DVC generated (contract)            → EN_ATTENTE_SIGNATURE
 *   nothing                               → A_GENERER
 *
 * Called after a contract is generated / deleted and after a document is
 * uploaded / deleted.
 */
class LeadDvcStatus
{
    public function refresh(Lead $lead): Lead
    {
        $signed = LeadDocument::query()
            ->where('lead_id', $lead->id)
            ->where('document_type', DocumentTypeEnum::DVC->value)
            ->first();

        if ($signed) {
            $status = DvcStatusEnum::SIGNE;
        } elseif (Contract::query()->where('lead_id', $lead->id)->exists()) {
            $status = DvcStatusEnum::EN_ATTENTE_SIGNATURE;
        } else {
            $status = DvcStatusEnum::A_GENERER;
        }

        $lead->forceFill([
            'dvc_status' => $status,
            'dvc_signed_at' => $signed?->created_at,
        ])->save();

        return $lead;
    }
}
