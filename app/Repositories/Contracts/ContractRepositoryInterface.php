<?php

namespace App\Repositories\Contracts;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ContractRepositoryInterface extends RepositoryInterface
{
    /**
     * Paginate contracts, newest first, optionally narrowed by a
     * visibility scope closure.
     */
    public function paginateScoped(array $filters, int $perPage = 15, ?Closure $scope = null): LengthAwarePaginator;

    /**
     * Count contracts already generated for the given lead + template
     * combination (used for versioning).
     */
    public function countForLeadAndTemplate(?int $leadId, string $templateKey): int;

    /**
     * Whether the given reference is already taken.
     */
    public function referenceExists(string $reference): bool;
}
