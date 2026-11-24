<?php

namespace App\Models;

use App\Support\InsuranceTypeCatalog;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An insurance type managed by the super admin. Leads store its code.
 */
class InsuranceType extends Model
{
    use LogsActivity;

    /** « Journal d'activité » */
    protected string $activityCategory = 'settings';

    /** Codes some business rules still look at */
    public const AUTO = 'AUTO';

    public const MOTO = 'MOTO';

    public const DECENNALE = 'DECENNALE';

    public const AUTRE = 'AUTRE';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'label',
        'short_label',
        'lead_page_id',
        'is_active',
        'requires_company_info',
        'is_system',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'requires_company_info' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(InsuranceTypeCatalog::class)->forget());
        static::deleted(fn () => app(InsuranceTypeCatalog::class)->forget());
    }

    public function leadPage(): BelongsTo
    {
        return $this->belongsTo(LeadPage::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'insurance_type', 'code');
    }
}
