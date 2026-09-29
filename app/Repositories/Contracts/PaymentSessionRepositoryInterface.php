<?php

namespace App\Repositories\Contracts;

use App\Models\PaymentSession;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface PaymentSessionRepositoryInterface extends RepositoryInterface
{
    /**
     * All sessions, newest first, with their lead and creator. $leadScope
     * narrows them to the leads the user may see (applied on the lead).
     *
     * @param  array{search?: string, status?: string, lead_id?: int|string}  $filters
     */
    public function paginateScoped(array $filters, int $perPage = 15, ?Closure $leadScope = null): LengthAwarePaginator;

    /**
     * Sessions of a lead, newest first, with their creator.
     */
    public function listForLead(int $leadId): Collection;

    /**
     * The lead's session that is still pending (waiting for the client, or
     * to check with Hyperswitch), if any.
     */
    public function findOpenForLead(int $leadId): ?PaymentSession;

    /**
     * How many sessions the lead already had (used to number the next one).
     */
    public function countForLead(int $leadId): int;

    /**
     * The session of a result-page token (by its SHA-256 hash).
     */
    public function findByTokenHash(string $hash): ?PaymentSession;

    /**
     * Pending sessions whose next Hyperswitch sync is due, oldest first.
     * Planned forced syncs must be due now; normal reads may be due within
     * $normalToleranceSeconds.
     *
     * @return Collection<int, PaymentSession>
     */
    public function dueForSync(int $limit = 50, int $normalToleranceSeconds = 0): Collection;
}
