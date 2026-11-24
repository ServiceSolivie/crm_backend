<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadNote extends Model
{
    use LogsActivity;

    /** « Journal d'activité » */
    protected string $activityCategory = 'lead';

    protected function activitySubject(): ?Model
    {
        return $this->lead ?? $this;
    }

    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'lead_id',
        'user_id',
        'note',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
