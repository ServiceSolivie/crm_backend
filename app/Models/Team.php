<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Team extends Model
{
    use Filterable, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'manager_id',
        'leader_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class, 'team_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'team_id');
    }

    /**
     * Appointments belonging to this team, via its leads.
     * An appointment belongs to a lead; the lead belongs to a team.
     */
    public function appointments(): HasManyThrough
    {
        return $this->hasManyThrough(Appointment::class, Lead::class, 'team_id', 'lead_id');
    }
}
