<?php

namespace App\Models;

use App\Enums\ActivityCategoryEnum;
use App\Enums\ActivityEventEnum;
use App\Enums\ActivityLevelEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing that happened to an operation of the activity journal.
 */
class ActivityLog extends Model
{
    const UPDATED_AT = null;

    /** Who did it, when it is not a CRM user */
    public const ACTOR_USER = 'user';

    public const ACTOR_SYSTEM = 'system';

    public const ACTOR_CLIENT = 'client';

    public const ACTOR_GOOGLE_ADS = 'google_ads';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'activity_operation_id',
        'event',
        'category',
        'level',
        'message',
        'actor_type',
        'actor_id',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'event' => ActivityEventEnum::class,
            'category' => ActivityCategoryEnum::class,
            'level' => ActivityLevelEnum::class,
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ActivityOperation::class, 'activity_operation_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
