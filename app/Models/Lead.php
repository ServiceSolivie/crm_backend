<?php

namespace App\Models;

use App\Casts\LeadStatusCast;
use App\Enums\AppointmentStatusEnum;
use App\Enums\ClientTypeEnum;
use App\Enums\DvcStatusEnum;
use App\Enums\InsuranceTypeEnum;
use App\Enums\LeadStatusEnum;
use App\Enums\PaymentRecordStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Support\LeadStatusCatalog;
use App\Support\PhoneNumber;
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
        'campaign_id',
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
            // The system status, or null for a custom one (see status_code)
            'status' => LeadStatusCast::class,
            'lead_submitted_at' => 'datetime',
            'expected_revenue' => 'decimal:2',
            'payment_status' => PaymentStatusEnum::class,
            'validated_at' => 'datetime',
            'is_doublon' => 'boolean',
            'dvc_status' => DvcStatusEnum::class,
            'dvc_signed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Keep a normalised copy of the phone so calls can be matched to leads.
        static::saving(function (Lead $lead) {
            if ($lead->isDirty('phone') || $lead->phone_e164 === null) {
                $lead->phone_e164 = PhoneNumber::toE164($lead->phone);
            }
        });

        // Calls received from this number before the lead existed (or before
        // its number was corrected) now belong to it.
        static::saved(function (Lead $lead) {
            // wasChanged() is only filled on updates, hence wasRecentlyCreated for new leads.
            if ($lead->phone_e164 !== null && ($lead->wasRecentlyCreated || $lead->wasChanged('phone_e164'))) {
                Call::whereNull('lead_id')
                    ->where('is_internal', false)
                    ->where('contact_number', $lead->phone_e164)
                    ->update(['lead_id' => $lead->id]);
            }
        });
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
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

    public function paymentSessions(): HasMany
    {
        return $this->hasMany(PaymentSession::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(LeadDocument::class);
    }

    /**
     * The status as stored, system or custom. `status` gives the enum case
     * for the rules; this gives the code for display and filters.
     */
    public function getStatusCodeAttribute(): ?string
    {
        return $this->attributes['status'] ?? null;
    }

    public function getStatusLabelAttribute(): ?string
    {
        return app(LeadStatusCatalog::class)->label($this->status_code);
    }

    /**
     * Money actually received: only payments with status REUSSI count
     * (pending, failed, cancelled and refunded ones do not).
     */
    public function getTotalReceivedAttribute(): string
    {
        $received = app(PaymentRepositoryInterface::class)->sumForLead($this->id, [PaymentRecordStatusEnum::REUSSI]);

        return bcadd($received, '0', 2);
    }

    public function getRemainingAmountAttribute(): string
    {
        if ($this->expected_revenue === null) {
            return '0.00';
        }

        return bcsub($this->expected_revenue, $this->total_received, 2);
    }

    /**
     * A lead can be called when it has a number (dialled as stored, whatever
     * its shape) and is not flagged as wrong.
     */
    public function isCallable(): bool
    {
        return PhoneNumber::forDialing($this->phone) !== null
            && ! in_array($this->status, [LeadStatusEnum::MAUVAIS_NUMERO, LeadStatusEnum::LEAD_INVALIDE], true);
    }

    /**
     * Phone calls made or received through Ringover (not the logged calls()).
     */
    public function phoneCalls(): HasMany
    {
        return $this->hasMany(Call::class);
    }
}
