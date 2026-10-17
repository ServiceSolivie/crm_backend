<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    use Filterable, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'reference',
        'template_key',
        'version',
        'dvc_type_version_id',
        'lead_id',
        'client_name',
        'data',
        'original_filename',
        'file_path',
        'mime_type',
        'file_size',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'data' => 'array',
            'file_size' => 'integer',
        ];
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * The DVC schema version the contract was filled with (null for the
     * contracts generated before DVC types existed).
     */
    public function dvcTypeVersion(): BelongsTo
    {
        return $this->belongsTo(DvcTypeVersion::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
