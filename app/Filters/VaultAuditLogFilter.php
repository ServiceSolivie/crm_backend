<?php

namespace App\Filters;

class VaultAuditLogFilter extends QueryFilter
{
    protected function action(string $value): void
    {
        $this->builder->where('action', $value);
    }

    protected function userId(string $value): void
    {
        $this->builder->where('user_id', $value);
    }

    protected function credentialId(string $value): void
    {
        $this->builder->where('credential_id', $value);
    }

    protected function from(string $value): void
    {
        $this->builder->where('created_at', '>=', $value);
    }

    protected function to(string $value): void
    {
        $this->builder->where('created_at', '<=', $value);
    }

    /** @return array<int, string> */
    protected function sortable(): array
    {
        return ['created_at'];
    }
}
