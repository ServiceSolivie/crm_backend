<?php

namespace App\Http\Controllers\Api\V1\Vault;

use App\Enums\PermissionEnum;
use App\Filters\VaultAuditLogFilter;
use App\Http\Controllers\Controller;
use App\Http\Resources\VaultAuditLogResource;
use App\Services\VaultAuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __construct(protected VaultAuditLogService $auditLogService) {}

    public function index(Request $request, VaultAuditLogFilter $filters): JsonResponse
    {
        if (! $request->user()->can(PermissionEnum::VAULT_AUDIT_LOGS_VIEW->value)) {
            abort(403);
        }

        $perPage = $request->integer('per_page', 25);
        $logs = $this->auditLogService->paginateFiltered($filters, $perPage);

        return $this->success(VaultAuditLogResource::collection($logs));
    }
}
