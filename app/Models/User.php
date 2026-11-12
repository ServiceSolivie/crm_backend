<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleEnum;
use App\Traits\Filterable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Filterable, HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'phone',
        'team_id',
        'last_login_at',
        'ringover_user_id',
        'ringover_number',
        'ringover_linked_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'ringover_linked_at' => 'datetime',
        ];
    }

    public function isRingoverLinked(): bool
    {
        return $this->ringover_user_id !== null;
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function managedTeam(): HasMany
    {
        return $this->hasMany(Team::class, 'manager_id');
    }

    public function ledTeam(): HasMany
    {
        return $this->hasMany(Team::class, 'leader_id');
    }

    public function assignedLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'assigned_to');
    }

    public function gestionLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'gestion_assigned_to');
    }

    public function createdLeads(): HasMany
    {
        return $this->hasMany(Lead::class, 'created_by');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'agent_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'created_by');
    }

    /**
     * Payments received on the leads assigned to this user.
     */
    public function assignedLeadPayments(): HasManyThrough
    {
        return $this->hasManyThrough(Payment::class, Lead::class, 'assigned_to', 'lead_id');
    }

    /**
     * Lead sources a marketing user is limited to.
     */
    public function leadSources(): BelongsToMany
    {
        return $this->belongsToMany(LeadSource::class)->withTimestamps();
    }

    /**
     * Source ids this user's data is limited to, or null for no limit.
     * Only marketing users with at least one source are limited.
     *
     * @return list<int>|null
     */
    public function allowedSourceIds(): ?array
    {
        if (! $this->hasRole(RoleEnum::MARKETING->value)) {
            return null;
        }

        $ids = $this->leadSources()->pluck('lead_sources.id')->map(fn ($id) => (int) $id)->all();

        return $ids ?: null;
    }

    public function vaultCredentials(): BelongsToMany
    {
        return $this->belongsToMany(Credential::class, 'user_credential')->withTimestamps();
    }

    /**
     * Phone calls made or received through Ringover.
     */
    public function phoneCalls(): HasMany
    {
        return $this->hasMany(Call::class, 'user_id');
    }
}
