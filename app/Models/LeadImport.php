<?php

namespace App\Models;

use App\Enums\LeadImportStatusEnum;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeadImport extends Model
{
    use Filterable, HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'file_name',
        'imported_by',
        'total_rows',
        'success_rows',
        'failed_rows',
        'error_report_path',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadImportStatusEnum::class,
        ];
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'lead_import_id');
    }
}
