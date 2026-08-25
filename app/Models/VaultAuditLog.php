<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaultAuditLog extends Model
{
    use Filterable;

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'credential_id',
        'action',
        'domain',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }
}
