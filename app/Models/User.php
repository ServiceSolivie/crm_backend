<?php

namespace App\Models;

use App\Enums\PermissionEnum;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RoleEnum;
use App\Traits\Filterable;
use App\Traits\LogsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use LogsActivity;

    /** « Journal d'activité » */
    protected string $activityCategory = 'user';

    /** @var array<int, string> */
    protected array $activityIgnore = ['last_login_at', 'ringover_linked_at'];

    /** @var array<string, class-string> */
    protected array $activityRefs = ['team_id' => Team::class];

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

    /** The technical account that owns leads coming from the sheets and imports */
    public const SYSTEM_EMAIL = 'import-automatique@crm.system';

    /**
     * « CRM – Import automatique »: created on first use, never active (it
     * can't log in). Leads from the sheets and file imports belong to it, so
     * no real person owns tens of thousands of leads.
     */
    public static function system(): self
    {
        return static::query()->firstOrCreate(
            ['email' => self::SYSTEM_EMAIL],
            ['name' => 'CRM – Import automatique', 'password' => Str::password(40), 'is_active' => false],
        );
    }

    public function isSystem(): bool
    {
        return $this->email === self::SYSTEM_EMAIL;
    }

    /**
     * « Superviseur »: an agent with the team-stats permission. He follows
     * his team (dashboard, reports, gestion, exports) without managing it.
     */
    public function isSupervisor(): bool
    {
        return $this->hasRole(RoleEnum::AGENT->value)
            && $this->can(PermissionEnum::DASHBOARD_VIEW_TEAM_STATS->value);
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
