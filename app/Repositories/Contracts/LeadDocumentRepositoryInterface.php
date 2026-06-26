<?php

namespace App\Repositories\Contracts;

use App\Enums\DocumentTypeEnum;
use Illuminate\Database\Eloquent\Model;

interface LeadDocumentRepositoryInterface extends RepositoryInterface
{
    public function findByLeadAndType(int $leadId, DocumentTypeEnum $type): ?Model;
}
