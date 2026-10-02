<?php

namespace App\Models;

use App\Enums\InsuranceTypeEnum;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use Filterable;

    protected $fillable = ['lead_source_id', 'name', 'external_campaign_id', 'form_id', 'form_name', 'insurance_type', 'is_active'];

    protected function casts(): array
    {
        return ['insurance_type' => InsuranceTypeEnum::class, 'is_active' => 'boolean'];
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function touchpoints(): HasMany
    {
        return $this->hasMany(LeadTouchpoint::class);
    }
}
