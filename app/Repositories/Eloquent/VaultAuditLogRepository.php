<?php

namespace App\Repositories\Eloquent;

use App\Filters\VaultAuditLogFilter;
use App\Models\VaultAuditLog;
use App\Repositories\Contracts\VaultAuditLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VaultAuditLogRepository extends BaseRepository implements VaultAuditLogRepositoryInterface
{
    public function model(): string
    {
        return VaultAuditLog::class;
    }

    public function paginateFiltered(VaultAuditLogFilter $filters, int $perPage = 25): LengthAwarePaginator
    {
        return $this->newQuery()
            ->with(['user:id,name', 'credential:id,label'])
            ->filter($filters)
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
