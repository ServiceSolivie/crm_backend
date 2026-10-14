<?php

namespace App\Repositories\Contracts;

use Illuminate\Database\Eloquent\Collection;

interface TrustedDeviceRepositoryInterface extends RepositoryInterface
{
    /**
     * Every machine, the waiting ones first, then the most recently used.
     */
    public function allForAdmin(): Collection;

    /**
     * @return array{pending: int, approved: int, revoked: int}
     */
    public function countsByStatus(): array;
}
