<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Base class for per-resource query filters.
 *
 * Subclasses define one public method per supported filter key (camelCase
 * of the query key) plus a sortable() whitelist for sorting.
 *
 * Filters can be passed either flat (?status=VALIDE&team_id=3) or nested
 * under filter[...] (?filter[status]=VALIDE&filter[team_id]=3).
 * Sorting can be passed either as ?sort=-created_at,reference or as
 * ?sort_by=created_at&sort_dir=desc.
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
        } elseif ($sortBy = $this->request->query('sort_by')) {
            $direction = $this->request->query('sort_dir', 'asc');
            $this->applySort((strtolower($direction) === 'desc' ? '-' : '').$sortBy);
        }

        return $this->builder;
    }

    /**
     * The filter values from the request, merging top-level query params
     * (e.g. ?status=VALIDE) with the nested filter[...] array
     * (e.g. ?filter[status]=VALIDE), so both styles are supported.
     */
    protected function filters(): array
    {
        $flat = $this->request->except(['filter', 'sort', 'sort_by', 'sort_dir', 'page', 'per_page']);

        return array_merge($flat, (array) $this->request->query('filter', []));
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
