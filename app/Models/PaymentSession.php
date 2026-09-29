<?php

namespace App\Models;

use App\Enums\PaymentSessionStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentSession extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'reference',
        'amount',
        'currency',
        'status',
        'provider_status',
        'error_code',
        'error_message',
        'hyperswitch_payment_id',
        'merchant_connector_id',
        'connector_transaction_id',
        'payment_url',
        'public_token_hash',
        'client_email',
        'sent_at',
        'returned_at',
        'paid_at',
        'last_synced_at',
        'last_forced_sync_at',
        'next_sync_at',
        'sync_attempts',
        'forced_syncs',
        'force_pending',
        'provider_payload',
        'created_by',
    ];

    /**
     * Never serialised: only its hash is stored, but keep it out anyway.
     *
     * @var list<string>
     */
    protected $hidden = ['public_token_hash'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentSessionStatusEnum::class,
            'sent_at' => 'datetime',
            'returned_at' => 'datetime',
            'paid_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_forced_sync_at' => 'datetime',
            'next_sync_at' => 'datetime',
            'sync_attempts' => 'integer',
            'forced_syncs' => 'integer',
            'force_pending' => 'boolean',
            'provider_payload' => 'array',
        ];
    }

    public function isPending(): bool
    {
        return in_array($this->status, PaymentSessionStatusEnum::pending(), true);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Payment attempts recorded for this request (from Hyperswitch).
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
