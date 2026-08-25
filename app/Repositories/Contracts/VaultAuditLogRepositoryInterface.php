<?php

namespace App\Repositories\Contracts;

use App\Filters\VaultAuditLogFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface VaultAuditLogRepositoryInterface extends RepositoryInterface
{
    public function paginateFiltered(VaultAuditLogFilter $filters, int $perPage = 25): LengthAwarePaginator;
}
