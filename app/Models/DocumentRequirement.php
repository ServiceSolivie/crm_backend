<?php

namespace App\Models;

use App\Enums\ClientTypeEnum;
use App\Enums\InsuranceTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRequirement extends Model
{
    protected $fillable = [
        'insurance_type',
        'client_type',
        'document_type_id',
    ];

    protected function casts(): array
    {
        return [
            'insurance_type' => InsuranceTypeEnum::class,
            'client_type' => ClientTypeEnum::class,
        ];
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }
}
