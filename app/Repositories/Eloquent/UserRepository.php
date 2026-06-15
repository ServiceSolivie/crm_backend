<?php

namespace App\Repositories\Eloquent;

use App\Filters\UserFilter;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function model(): string
    {
        return User::class;
    }

    public function paginateFiltered(UserFilter $filters, int $perPage = 15, ?Closure $scope = null): LengthAwarePaginator
    {
        $query = $this->newQuery()->with(['roles', 'team']);

        if ($scope) {
            $scope($query);
        }

        return $query->filter($filters)->paginate($perPage);
    }
}
