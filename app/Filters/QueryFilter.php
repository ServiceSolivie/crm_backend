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

            if (method_exists($this, $method) && ! $this->isInternal($method)) {
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
     * Helper and sort methods that must never be reachable from a query key.
     */
    protected function isInternal(string $method): bool
    {
        return str_starts_with($method, 'sortBy')
            || in_array($method, ['apply', 'filters', 'applySort', 'sortable', 'values', 'whereIn', 'isInternal'], true);
    }

    /**
     * A filter value as a list: accepts ?key=a, ?key=a,b and ?key[]=a&key[]=b.
     *
     * @return array<int, string>
     */
    protected function values(string|array $value): array
    {
        $list = is_array($value) ? $value : explode(',', $value);

        return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $list), fn ($v) => $v !== ''));
    }

    /**
     * where() for one value, whereIn() for several.
     */
    protected function whereIn(string $column, string|array $value): void
    {
        $values = $this->values($value);

        if (count($values) === 1) {
            $this->builder->where($column, $values[0]);
        } elseif ($values) {
            $this->builder->whereIn($column, $values);
        }
    }

    /**
     * Apply the `?sort=field,-other_field` query parameter.
     */
    protected function applySort(string $sort): void
    {
        foreach (explode(',', $sort) as $field) {
            $direction = Str::startsWith($field, '-') ? 'desc' : 'asc';
            $column = ltrim($field, '-');

            $custom = 'sortBy'.Str::studly($column);

            if (method_exists($this, $custom)) {
                $this->$custom($direction);
            } elseif (in_array($column, $this->sortable(), true)) {
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
