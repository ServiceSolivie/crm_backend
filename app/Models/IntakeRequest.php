<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One request received on the marketing intake endpoint, as it arrived.
 */
class IntakeRequest extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'received_at',
        'method',
        'content_type',
        'size',
        'body',
        'query',
        'headers',
        'ip',
        'key_valid',
        'key_via',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'size' => 'integer',
            'query' => 'array',
            'headers' => 'array',
            'key_valid' => 'boolean',
        ];
    }
}
