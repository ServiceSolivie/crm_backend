<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Partner extends Model
{
    use Filterable, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'login_url',
        'domain',
        'field_mapping',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'field_mapping' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }
}
