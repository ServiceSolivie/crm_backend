<?php

namespace App\Filters;

/**
 * Supported query parameters:
 *
 *   GET /users?filter[role]=agent
 *   GET /users?filter[team_id]=3
 *   GET /users?filter[is_active]=1
 *   GET /users?filter[search]=john        (matches name or email)
 *   GET /users?sort=-created_at,name
 */
class UserFilter extends QueryFilter
{
    protected function role(string $value): void
    {
        $this->builder->whereHas('roles', fn ($query) => $query->where('name', $value));
    }

    protected function teamId(string $value): void
    {
        $this->builder->where('team_id', $value);
    }

    protected function isActive(string $value): void
    {
        $this->builder->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    protected function search(string $value): void
    {
        // "25" or "#25": the user ID (as shown in Slack errors)
        $id = ltrim(trim($value), '#');

        $this->builder->where(function ($query) use ($value, $id) {
            $query->where('name', 'like', "%{$value}%")
                ->orWhere('email', 'like', "%{$value}%");

            if (ctype_digit($id)) {
                $query->orWhere('id', (int) $id);
            }
        });
    }

    /**
     * @return array<int, string>
     */
    protected function sortable(): array
    {
        return [
            'name',
            'email',
            'created_at',
            'last_login_at',
        ];
    }
}
