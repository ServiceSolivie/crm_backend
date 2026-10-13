<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface LeadStatusRepositoryInterface extends RepositoryInterface
{
    /**
     * The custom statuses with the number of leads currently in each.
     */
    public function allWithLeadCounts(): Collection;

    /**
     * Is the code on a lead (deleted ones included) or in a lead's history?
     */
    public function isUsed(string $code): bool;
}
