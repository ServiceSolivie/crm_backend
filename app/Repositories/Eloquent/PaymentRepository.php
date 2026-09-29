<?php

namespace App\Repositories\Eloquent;

use App\Enums\PaymentRecordStatusEnum;
use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PaymentRepository extends BaseRepository implements PaymentRepositoryInterface
{
    public function model(): string
    {
        return Payment::class;
    }

    public function paginateForLead(int $leadId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()
            ->where('lead_id', $leadId)
            ->with(['creator', 'statusChanger'])
            ->latest('payment_date')
            ->latest('id')
            ->paginate($perPage);
    }

    public function sumForLead(int $leadId, array $statuses): string
    {
        return (string) ($this->newQuery()
            ->where('lead_id', $leadId)
            ->whereIn('status', array_map(fn (PaymentRecordStatusEnum $s) => $s->value, $statuses))
            ->sum('amount') ?: '0');
    }

    public function findByExternalId(string $externalId): ?Payment
    {
        return $this->newQuery()->where('external_id', $externalId)->first();
    }

    public function existsForLead(int $leadId, ?PaymentRecordStatusEnum $status = null): bool
    {
        return $this->newQuery()
            ->where('lead_id', $leadId)
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->exists();
    }
}
