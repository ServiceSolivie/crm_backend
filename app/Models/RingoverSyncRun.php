<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RingoverSyncRun extends Model
{
    protected $fillable = [
        'window_from',
        'window_to',
        'status',
        'calls_synced',
        'pending_webhooks_processed',
        'error',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'window_from' => 'datetime',
            'window_to' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public static function lastSuccessful(): ?self
    {
        return static::where('status', 'success')->latest('window_to')->first();
    }
}
