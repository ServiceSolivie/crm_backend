<?php

namespace App\Models;

use App\Enums\ClientTypeEnum;
use App\Enums\InsuranceTypeEnum;
use App\Enums\LeadStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'team_id',
        'created_by',
        'lead_import_id',
        'comment',
        'expected_revenue',
        'payment_status',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'insurance_type' => InsuranceTypeEnum::class,
            'client_type' => ClientTypeEnum::class,
            'status' => LeadStatusEnum::class,
            'expected_revenue' => 'decimal:2',
            'payment_status' => PaymentStatusEnum::class,
            'validated_at' => 'datetime',
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

    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(LeadStatusHistory::class)->latest();
    }

    public function assignmentHistories(): HasMany
    {
        return $this->hasMany(LeadAssignmentHistory::class)->latest();
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

    public function getTotalReceivedAttribute(): string
    {
        return $this->payments()->sum('amount');
    }

    public function getRemainingAmountAttribute(): string
    {
        if ($this->expected_revenue === null) {
            return '0.00';
        }

        return bcsub($this->expected_revenue, $this->total_received, 2);
    }
}
