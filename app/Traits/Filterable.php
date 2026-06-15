<?php

namespace App\Traits;

use App\Filters\QueryFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * Add to a model to enable: Model::query()->filter(new SomeQueryFilter($request))
 */
trait Filterable
{
    public function scopeFilter(Builder $query, QueryFilter $filters): Builder
    {
        return $filters->apply($query);
    }
}
