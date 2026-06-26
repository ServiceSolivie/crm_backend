<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;

interface LeadDocumentRepositoryInterface extends RepositoryInterface
{
    public function findByLeadAndType(int $leadId, string $type): ?Model;
}
