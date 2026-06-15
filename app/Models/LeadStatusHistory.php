<?php

namespace App\Models;

use App\Enums\LeadStatusEnum;
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
    ];

    protected function casts(): array
    {
        return [
            'from_status' => LeadStatusEnum::class,
            'to_status' => LeadStatusEnum::class,
        ];
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
