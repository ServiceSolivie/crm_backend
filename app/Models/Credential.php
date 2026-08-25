<?php

namespace App\Models;

use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Credential extends Model
{
    use Filterable, HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'partner_id',
        'label',
        'username_encrypted',
        'email_encrypted',
        'password_encrypted',
        'extra_fields_encrypted',
        'is_active',
    ];

    /** @var list<string> */
    protected $hidden = [
        'username_encrypted',
        'email_encrypted',
        'password_encrypted',
        'extra_fields_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'username_encrypted' => 'encrypted',
            'email_encrypted' => 'encrypted',
            'password_encrypted' => 'encrypted',
            'extra_fields_encrypted' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_credential')->withTimestamps();
    }
}
