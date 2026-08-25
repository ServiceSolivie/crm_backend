<?php

namespace App\Repositories\Eloquent;

use App\Filters\CredentialFilter;
use App\Models\Credential;
use App\Repositories\Contracts\CredentialRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CredentialRepository extends BaseRepository implements CredentialRepositoryInterface
{
    public function model(): string
    {
        return Credential::class;
    }

    public function paginateFiltered(CredentialFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()
            ->with('partner:id,name')
            ->filter($filters)
            ->paginate($perPage);
    }
}
