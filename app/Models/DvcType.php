<?php

namespace App\Models;

use App\Enums\InsuranceTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The DVC of one insurance type. Its form and its PDF are described by the
 * JSON schema of its current version (see DvcSchema); the super admin edits
 * that schema from the CRM, no code per type.
 */
class DvcType extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'insurance_type',
        'label',
        'filename_prefix',
        'is_active',
        'current_version_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'insurance_type' => InsuranceTypeEnum::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Key stored on contracts.template_key. "auto_dvc" and "decennale_dvc"
     * are the keys the hard-coded templates used, so older contracts and
     * their per-lead version count line up.
     */
    public function templateKey(): string
    {
        return strtolower($this->insurance_type->value).'_dvc';
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DvcTypeVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DvcTypeVersion::class)->orderByDesc('version');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
