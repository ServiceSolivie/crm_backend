<?php

namespace App\Models;

use App\Enums\DocumentTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    protected $fillable = [
        'name',
        'label',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Types the CRM relies on (the signed DVC): required for every product,
     * cannot be deleted, deactivated or renamed to another code.
     */
    public function isSystem(): bool
    {
        return $this->name === DocumentTypeEnum::DVC->value;
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(DocumentRequirement::class);
    }
}
