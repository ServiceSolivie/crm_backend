<?php

namespace App\Repositories\Eloquent;

use App\Enums\PaymentRefundStatusEnum;
use App\Models\PaymentRefund;
use App\Repositories\Contracts\PaymentRefundRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class PaymentRefundRepository extends BaseRepository implements PaymentRefundRepositoryInterface
{
    public function model(): string
    {
        return PaymentRefund::class;
    }

    public function findBlockingForSession(int $sessionId): ?PaymentRefund
    {
        return $this->newQuery()
            ->where('payment_session_id', $sessionId)
            ->whereIn('status', array_map(fn ($s) => $s->value, PaymentRefundStatusEnum::blocking()))
            ->latest('id')
            ->first();
    }

    public function dueForSync(int $limit = 50): Collection
    {
        return $this->newQuery()
            ->whereIn('status', array_map(fn ($s) => $s->value, PaymentRefundStatusEnum::pending()))
            ->whereNotNull('next_sync_at')
            ->where('next_sync_at', '<=', now())
            ->orderBy('next_sync_at')
            ->limit($limit)
            ->get();
    }
}
