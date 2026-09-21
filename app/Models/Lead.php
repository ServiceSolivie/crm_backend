<?php

namespace App\Models;

use App\Enums\AppointmentStatusEnum;
use App\Enums\ClientTypeEnum;
use App\Enums\DvcStatusEnum;
use App\Enums\InsuranceTypeEnum;
use App\Enums\LeadStatusEnum;
use App\Enums\PaymentRecordStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use Filterable, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'first_name',
        'last_name',
        'phone',
        'email',
        'city',
        'address',
        'birth_date',
        'lead_source_id',
        'insurance_type',
        'client_type',
        'company_status',
        'company_legal_form',
        'company_sector',
        'company_employee_count',
        'company_name',
        'company_annual_revenue',
        'status',
        'assigned_to',
        'gestion_assigned_to',
        'team_id',
        'created_by',
        'lead_import_id',
        'comment',
        'lead_submitted_at',
        'expected_revenue',
        'payment_status',
        'validated_at',
        'sheet_row_key',
        'is_doublon',
        'doublon_of_lead_id',
        'dvc_status',
        'dvc_signed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'dvc_status' => 'A_GENERER',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'insurance_type' => InsuranceTypeEnum::class,
            'client_type' => ClientTypeEnum::class,
            'status' => LeadStatusEnum::class,
            'lead_submitted_at' => 'datetime',
            'expected_revenue' => 'decimal:2',
            'payment_status' => PaymentStatusEnum::class,
            'validated_at' => 'datetime',
            'is_doublon' => 'boolean',
            'dvc_status' => DvcStatusEnum::class,
            'dvc_signed_at' => 'datetime',
        ];
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function gestionAssignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gestion_assigned_to');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function leadImport(): BelongsTo
    {
        return $this->belongsTo(LeadImport::class);
    }

    public function doublonOf(): BelongsTo
    {
        return $this->belongsTo(Lead::class, 'doublon_of_lead_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
    }

    public function calls(): HasMany
    {
        return $this->hasMany(LeadCall::class)->latest();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class)->latest();
    }

    public function assignmentHistories(): HasMany
    {
        return $this->hasMany(LeadAssignmentHistory::class)->latest();
    }

    /**
     * The most recent time gestion sent this lead back for correction.
     */
    public function lastFlag(): HasOne
    {
        return $this->hasOne(LeadStatusHistory::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->where('to_status', LeadStatusEnum::A_CORRIGER->value),
        );
    }

    /**
     * The earliest appointment still to happen (may already be late).
     */
    public function nextAppointment(): HasOne
    {
        return $this->hasOne(Appointment::class)->ofMany(
            ['scheduled_at' => 'min', 'id' => 'min'],
            fn ($query) => $query->whereIn('status', AppointmentStatusEnum::openValues()),
        );
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LeadDocument::class);
    }

    /**
     * Money actually received: only payments with status REUSSI count
     * (pending, failed, cancelled and refunded ones do not).
     */
    public function getTotalReceivedAttribute(): string
    {
        return (string) ($this->payments()->where('status', PaymentRecordStatusEnum::REUSSI->value)->sum('amount') ?: '0.00');
    }

    public function getRemainingAmountAttribute(): string
    {
        if ($this->expected_revenue === null) {
            return '0.00';
        }

        return bcsub($this->expected_revenue, $this->total_received, 2);
    }
}
