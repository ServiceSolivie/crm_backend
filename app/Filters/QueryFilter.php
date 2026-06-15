<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Base class for per-resource query filters.
 *
 * Subclasses define one public method per supported `filter[...]` key
 * (camelCase of the query key) plus a sortable() whitelist for `?sort=`.
 *
 * Example request: GET /leads?filter[status]=VALIDE&filter[team_id]=3&sort=-created_at
 */
abstract class QueryFilter
{
    protected Builder $builder;

    public function __construct(protected Request $request) {}

    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->filters() as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $method = Str::camel($key);

            if (method_exists($this, $method)) {
                $this->$method($value);
            }
        }

        if ($sort = $this->request->query('sort')) {
            $this->applySort($sort);
        }

        return $this->builder;
    }

    /**
     * The raw filter[...] array from the request.
     */
    protected function filters(): array
    {
        return (array) $this->request->query('filter', []);
    }

    /**
     * Apply the `?sort=field,-other_field` query parameter.
     */
    protected function applySort(string $sort): void
    {
        foreach (explode(',', $sort) as $field) {
            $direction = Str::startsWith($field, '-') ? 'desc' : 'asc';
            $column = ltrim($field, '-');

            if (in_array($column, $this->sortable(), true)) {
                $this->builder->orderBy($column, $direction);
            }
        }
    }

    /**
     * Whitelist of columns that may be used in `?sort=`.
     *
     * @return array<int, string>
     */
    protected function sortable(): array
    {
        return [];
    }
}
