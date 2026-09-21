<?php

namespace App\Models;

use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentRecordStatusEnum;
use App\Enums\PaymentSourceEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'amount',
        'status',
        'source',
        'external_id',
        'payment_date',
        'payment_method',
        'custom_payment_method',
        'reference_number',
        'notes',
        'failure_reason',
        'provider_payload',
        'status_changed_at',
        'status_changed_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'payment_method' => PaymentMethodEnum::class,
            'status' => PaymentRecordStatusEnum::class,
            'source' => PaymentSourceEnum::class,
            'provider_payload' => 'array',
            'status_changed_at' => 'datetime',
        ];
    }

    /**
     * Money actually received (not pending, failed, cancelled or refunded).
     */
    public function scopeReceived($query)
    {
        return $query->where('payments.status', PaymentRecordStatusEnum::REUSSI->value);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function statusChanger(): BelongsTo
    {
        return $this->belongsTo(User::class, 'status_changed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
