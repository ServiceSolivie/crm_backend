<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{
    /**
     * Get all records.
     */
    public function all(array $columns = ['*']): Collection;

    /**
     * Get a paginated list of records.
     */
    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator;

    /**
     * Find a record by its primary key.
     */
    public function find(int|string $id, array $columns = ['*']): ?Model;

    /**
     * Find a record by its primary key or fail.
     */
    public function findOrFail(int|string $id, array $columns = ['*']): Model;

    /**
     * Find the first record matching a field/value pair.
     */
    public function findBy(string $field, mixed $value, array $columns = ['*']): ?Model;

    /**
     * Create a new record.
     */
    public function create(array $attributes): Model;

    /**
     * Update an existing record.
     */
    public function update(int|string $id, array $attributes): Model;

    /**
     * Delete a record.
     */
    public function delete(int|string $id): bool;

    /**
     * Eager-load relations on the next query.
     */
    public function with(array $relations): static;

    /**
     * Get a fresh query builder instance for the underlying model.
     */
    public function newQuery(): Builder;
}
