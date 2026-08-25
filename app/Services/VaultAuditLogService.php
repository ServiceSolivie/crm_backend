<?php

namespace App\Services;

use App\Filters\VaultAuditLogFilter;
use App\Repositories\Contracts\VaultAuditLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VaultAuditLogService extends BaseService
{
    public function __construct(protected VaultAuditLogRepositoryInterface $logs)
    {
        parent::__construct($logs);
    }

    public function paginateFiltered(VaultAuditLogFilter $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->logs->paginateFiltered($filters, $perPage);
    }
}
