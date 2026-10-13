<?php

namespace App\Models;

use App\Casts\LeadStatusCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadStatusHistory extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'from_status',
        'to_status',
        'changed_by',
        'comment',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            // The system status, or null for a custom one (see *_code)
            'from_status' => LeadStatusCast::class,
            'to_status' => LeadStatusCast::class,
            'meta' => 'array',
        ];
    }

    public function getFromStatusCodeAttribute(): ?string
    {
        return $this->attributes['from_status'] ?? null;
    }

    public function getToStatusCodeAttribute(): ?string
    {
        return $this->attributes['to_status'] ?? null;
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
