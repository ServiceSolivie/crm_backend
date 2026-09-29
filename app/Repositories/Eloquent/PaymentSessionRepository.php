<?php

namespace App\Repositories\Eloquent;

use App\Enums\PaymentSessionStatusEnum;
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

    public function paginateScoped(array $filters, int $perPage = 15, ?Closure $leadScope = null): LengthAwarePaginator
    {
        $query = $this->newQuery()
            ->with(['lead:id,reference,first_name,last_name', 'creator:id,name'])
            ->latest('id');

        if ($leadScope) {
            $query->whereHas('lead', $leadScope);
        }

        if (! empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('client_email', 'like', "%{$search}%")
                    ->orWhere('hyperswitch_payment_id', 'like', "%{$search}%")
                    ->orWhereHas('lead', fn ($lead) => $lead
                        ->where('reference', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['lead_id'])) {
            $query->where('lead_id', $filters['lead_id']);
        }

        return $query->paginate($perPage);
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
