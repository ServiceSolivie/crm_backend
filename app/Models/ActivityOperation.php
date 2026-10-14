<?php

namespace App\Models;

use App\Enums\ActivityCategoryEnum;
use App\Enums\ActivityStateEnum;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One operation of the activity journal (a payment, a Google Ads
 * submission, an account's logins of the day…) and how it stands now.
 * Its logs are what happened to it, in order.
 */
class ActivityOperation extends Model
{
    use Filterable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'category',
        'has_refund',
        'title',
        'subtitle',
        'reference',
        'lead_id',
        'subject_type',
        'subject_id',
        'state',
        'state_label',
        'state_event',
        'logs_count',
        'problems_count',
        'started_at',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => ActivityCategoryEnum::class,
            'state' => ActivityStateEnum::class,
            'has_refund' => 'boolean',
            'logs_count' => 'integer',
            'problems_count' => 'integer',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * What kind of operation this is, from its key ("session:124" is a
     * payment): the interface names it in the user's language.
     */
    public function kind(): string
    {
        return match (true) {
            str_starts_with($this->key, 'session:') => 'payment',
            str_starts_with($this->key, 'lead-total:') => 'contract_total',
            str_starts_with($this->key, 'gads:rejected:') => 'google_ads_unauthenticated',
            str_starts_with($this->key, 'gads:') => 'google_ads',
            str_starts_with($this->key, 'auth:unknown:') => 'auth_unknown',
            str_starts_with($this->key, 'auth:') => 'auth',
            str_starts_with($this->key, 'user:') => 'user',
            str_starts_with($this->key, 'system:hyperswitch:') => 'hyperswitch',
            default => 'other',
        };
    }

    /**
     * What happened, oldest first.
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ActivityLog::class)->orderBy('id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
