<?php

namespace App\Models;

use App\Support\Dvc\DvcSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One saved state of a DVC type's schema. Never updated: publishing a change
 * creates the next version, and every contract keeps the version it was
 * generated with.
 */
class DvcTypeVersion extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'dvc_type_id',
        'version',
        'schema',
        'change_note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'schema' => 'array',
        ];
    }

    public function dvcSchema(): DvcSchema
    {
        return new DvcSchema($this->schema);
    }

    public function dvcType(): BelongsTo
    {
        return $this->belongsTo(DvcType::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
