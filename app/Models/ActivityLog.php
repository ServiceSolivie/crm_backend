<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the « Journal d'activité ». Written by App\Support\ActivityLogger,
 * never edited from the CRM.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /** How long lines are kept */
    public const RETENTION_MONTHS = 12;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
