<?php

namespace App\Models;

use App\Enums\PaymentRefundStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'payment_session_id',
        'payment_id',
        'hyperswitch_refund_id',
        'amount',
        'currency',
        'status',
        'reason',
        'connector_refund_id',
        'provider_status',
        'error_code',
        'error_message',
        'provider_payload',
        'last_synced_at',
        'next_sync_at',
        'sync_attempts',
        'refunded_at',
        'requested_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'status' => PaymentRefundStatusEnum::class,
            'provider_payload' => 'array',
            'last_synced_at' => 'datetime',
            'next_sync_at' => 'datetime',
            'sync_attempts' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return in_array($this->status, PaymentRefundStatusEnum::pending(), true);
    }

    public function paymentSession(): BelongsTo
    {
        return $this->belongsTo(PaymentSession::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
