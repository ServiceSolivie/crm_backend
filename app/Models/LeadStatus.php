<?php

namespace App\Models;

use App\Support\LeadStatusCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A custom lead status added from the CRM. The system statuses are not rows
 * of this table: they live in LeadStatusEnum.
 */
class LeadStatus extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'label',
        'stage',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(LeadStatusCatalog::class)->forget());
        static::deleted(fn () => app(LeadStatusCatalog::class)->forget());
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'status', 'code');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
