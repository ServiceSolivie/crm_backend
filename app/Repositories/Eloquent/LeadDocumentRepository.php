<?php

namespace App\Repositories\Eloquent;

use App\Models\LeadDocument;
use App\Repositories\Contracts\LeadDocumentRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class LeadDocumentRepository extends BaseRepository implements LeadDocumentRepositoryInterface
{
    public function model(): string
    {
        return LeadDocument::class;
    }

    public function findByLeadAndType(int $leadId, string $type): ?Model
    {
        return $this->newQuery()
            ->where('lead_id', $leadId)
            ->where('document_type', $type)
            ->first();
    }
}
