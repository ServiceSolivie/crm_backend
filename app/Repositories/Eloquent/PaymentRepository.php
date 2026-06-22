<?php

namespace App\Repositories\Eloquent;

use App\Models\Payment;
use App\Repositories\Contracts\PaymentRepositoryInterface;

class PaymentRepository extends BaseRepository implements PaymentRepositoryInterface
{
    public function model(): string
    {
        return Payment::class;
    }

    public function sumForLead(int $leadId): string
    {
        return (string) $this->newQuery()
            ->where('lead_id', $leadId)
            ->sum('amount');
    }
}
