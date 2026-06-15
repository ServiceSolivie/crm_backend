<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements RepositoryInterface
{
    /**
     * The model instance.
     */
    protected Model $model;

    /**
     * Relations to eager-load on the next query.
     *
     * @var array<int, string>
     */
    protected array $relations = [];

    public function __construct()
    {
        $this->model = app($this->model());
    }

    /**
     * Fully qualified class name of the Eloquent model this repository manages.
     */
    abstract public function model(): string;

    public function newQuery(): Builder
    {
        $query = $this->model->newQuery();

        if (! empty($this->relations)) {
            $query->with($this->relations);
            $this->relations = [];
        }

        return $query;
    }

    public function with(array $relations): static
    {
        $this->relations = $relations;

        return $this;
    }

    public function all(array $columns = ['*']): Collection
    {
        return $this->newQuery()->get($columns);
    }

    public function paginate(int $perPage = 15, array $columns = ['*']): LengthAwarePaginator
    {
        return $this->newQuery()->paginate($perPage, $columns);
    }

    public function find(int|string $id, array $columns = ['*']): ?Model
    {
        return $this->newQuery()->find($id, $columns);
    }

    public function findOrFail(int|string $id, array $columns = ['*']): Model
    {
        return $this->newQuery()->findOrFail($id, $columns);
    }

    public function findBy(string $field, mixed $value, array $columns = ['*']): ?Model
    {
        return $this->newQuery()->where($field, $value)->first($columns);
    }

    public function create(array $attributes): Model
    {
        return $this->model->newQuery()->create($attributes);
    }

    public function update(int|string $id, array $attributes): Model
    {
        $record = $this->model->newQuery()->findOrFail($id);
        $record->update($attributes);

        return $record->refresh();
    }

    public function delete(int|string $id): bool
    {
        return (bool) $this->model->newQuery()->findOrFail($id)->delete();
    }
}
