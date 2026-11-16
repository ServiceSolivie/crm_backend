<?php

namespace App\Models;

use App\Support\InsuranceTypeCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An extra leads page (Décennale, Flotte & Camion…). The insurance types
 * placed on it leave the « Leads » page and show here instead.
 */
class LeadPage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'sort_order',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => app(InsuranceTypeCatalog::class)->forget());
        static::deleted(fn () => app(InsuranceTypeCatalog::class)->forget());
    }

    public function insuranceTypes(): HasMany
    {
        return $this->hasMany(InsuranceType::class);
    }
}
