<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleSheetSyncLog extends Model
{
    protected $fillable = [
        'sheet_name',
        'total_rows',
        'imported',
        'skipped',
        'failed',
        'last_row_synced',
        'started_at',
        'completed_at',
        'error_details',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'error_details' => 'array',
        ];
    }
}
