<?php

namespace App\Models;

use App\Enums\CallDirectionEnum;
use App\Enums\CallStatusEnum;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Call extends Model
{
    use Filterable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'user_id',
        'team_id',
        'ringover_call_id',
        'ringover_channel_id',
        'direction',
        'status',
        'is_internal',
        'from_number',
        'to_number',
        'contact_number',
        'started_at',
        'answered_at',
        'ended_at',
        'duration_seconds',
        'talk_seconds',
        'recording_url',
        'recording_duration_seconds',
        'voicemail_url',
        'transcription_url',
        'ai_summary',
        'note',
        'source',
        'last_event',
    ];

    /**
     * Ringover links are only served through the CRM (permission-checked), never directly.
     *
     * @var list<string>
     */
    protected $hidden = [
        'recording_url',
        'voicemail_url',
        'transcription_url',
    ];

    protected function casts(): array
    {
        return [
            'direction' => CallDirectionEnum::class,
            'status' => CallStatusEnum::class,
            'is_internal' => 'boolean',
            'started_at' => 'datetime',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
            'talk_seconds' => 'integer',
            'recording_duration_seconds' => 'integer',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class)->withTrashed();
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
