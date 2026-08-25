<?php

namespace App\Services;

use App\Filters\CredentialFilter;
use App\Repositories\Contracts\CredentialRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;

class CredentialService extends BaseService
{
    public function __construct(protected CredentialRepositoryInterface $credentials)
    {
        parent::__construct($credentials);
    }

    public function paginateFiltered(CredentialFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->credentials->paginateFiltered($filters, $perPage);
    }

    public function update(int|string $id, array $attributes): Model
    {
        $filtered = array_filter($attributes, fn ($v) => $v !== null && $v !== '');

        return parent::update($id, $filtered);
    }
}
