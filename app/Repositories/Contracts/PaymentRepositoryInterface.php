<?php

namespace App\Repositories\Contracts;

use App\Enums\PaymentRecordStatusEnum;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentRepositoryInterface extends RepositoryInterface
{
    /**
     * Payments of a lead, newest first, with their creator and status changer.
     */
    public function paginateForLead(int $leadId, int $perPage = 15): LengthAwarePaginator;

    /**
     * Total amount of a lead's payments having one of the given statuses.
     *
     * @param  array<int, PaymentRecordStatusEnum>  $statuses
     */
    public function sumForLead(int $leadId, array $statuses): string;

    public function existsForLead(int $leadId, ?PaymentRecordStatusEnum $status = null): bool;

    /**
     * The payment recorded for a Hyperswitch payment id (payments.external_id).
     */
    public function findByExternalId(string $externalId): ?Payment;
}
