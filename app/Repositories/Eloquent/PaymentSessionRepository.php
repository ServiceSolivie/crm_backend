<?php

namespace App\Repositories\Eloquent;

use App\Enums\PaymentSessionStatusEnum;
use App\Filters\PaymentSessionFilter;
use App\Models\PaymentSession;
use App\Repositories\Contracts\PaymentSessionRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PaymentSessionRepository extends BaseRepository implements PaymentSessionRepositoryInterface
{
    public function model(): string
    {
        return PaymentSession::class;
    }

    public function paginateScoped(PaymentSessionFilter $filters, int $perPage = 15, ?Closure $leadScope = null): LengthAwarePaginator
    {
        return $this->newQuery()
            ->with(['lead:id,reference,first_name,last_name', 'creator:id,name'])
            ->when($leadScope, fn ($query) => $query->whereHas('lead', $leadScope))
            ->filter($filters)
            ->latest('id')
            ->paginate($perPage);
    }

    public function listForLead(int $leadId): Collection
    {
        return $this->newQuery()
            ->where('lead_id', $leadId)
            ->with('creator')
            ->latest('id')
            ->get();
    }

    public function findOpenForLead(int $leadId): ?PaymentSession
    {
        return $this->newQuery()
            ->where('lead_id', $leadId)
            ->whereIn('status', array_map(fn ($s) => $s->value, PaymentSessionStatusEnum::pending()))
            ->first();
    }

    public function countForLead(int $leadId): int
    {
        return $this->newQuery()->where('lead_id', $leadId)->count();
    }

    public function findByTokenHash(string $hash): ?PaymentSession
    {
        return $this->newQuery()->where('public_token_hash', $hash)->first();
    }

    public function dueForSync(int $limit = 50, int $normalToleranceSeconds = 0): Collection
    {
        $now = now();

        return $this->newQuery()
            ->whereIn('status', array_map(fn ($s) => $s->value, PaymentSessionStatusEnum::pending()))
            ->whereNotNull('hyperswitch_payment_id')
            ->where(fn ($query) => $query
                // Planned forced sync: exactly on time
                ->where(fn ($forced) => $forced->where('force_pending', true)->where('next_sync_at', '<=', $now))
                // Normal read: also when due within the tolerance
                ->orWhere(fn ($normal) => $normal->where('force_pending', false)->where('next_sync_at', '<=', $now->copy()->addSeconds($normalToleranceSeconds))))
            ->orderBy('next_sync_at')
            ->limit($limit)
            ->get();
    }
}
