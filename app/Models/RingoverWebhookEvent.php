<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Raw log of every webhook received from Ringover.
 */
class RingoverWebhookEvent extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'event',
        'ringover_call_id',
        'dedupe_key',
        'payload',
        'deliveries',
        'processed_at',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Ringover retries failed deliveries and adds an "attempt" counter;
     * the key ignores it so a retry is recognised as the same event.
     */
    public static function dedupeKeyFor(array $payload): string
    {
        unset($payload['attempt']);

        return sha1(json_encode($payload));
    }
}
