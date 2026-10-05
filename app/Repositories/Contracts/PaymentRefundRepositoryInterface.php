<?php

namespace App\Repositories\Contracts;

use App\Models\PaymentRefund;
use Illuminate\Database\Eloquent\Collection;

interface PaymentRefundRepositoryInterface extends RepositoryInterface
{
    /**
     * The refund of this session that blocks a new one (being checked,
     * waiting for the bank, or done), if any.
     */
    public function findBlockingForSession(int $sessionId): ?PaymentRefund;

    /**
     * Pending refunds whose next Hyperswitch check is due, oldest first.
     *
     * @return Collection<int, PaymentRefund>
     */
    public function dueForSync(int $limit = 50): Collection;
}
