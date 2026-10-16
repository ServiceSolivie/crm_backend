<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A "remember this machine" mark held by one browser of an approved machine.
 */
class TrustedDeviceMark extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'trusted_device_id',
        'token_hash',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(TrustedDevice::class, 'trusted_device_id');
    }
}
