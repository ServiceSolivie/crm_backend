<?php

namespace App\Models;

use App\Enums\TrustedDeviceStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A machine (one Windows account on one PC) known to the CRM by its passkey.
 */
class TrustedDevice extends Model
{
    /** Prefix of the API tokens issued through a machine: "device:12" */
    public const TOKEN_PREFIX = 'device:';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'label',
        'credential_id',
        'public_key',
        'sign_count',
        'status',
        'registered_by',
        'approved_by',
        'approved_at',
        'revoked_at',
        'revoked_reason',
        'last_used_at',
        'last_used_by',
        'last_ip',
        'user_agent',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'public_key',
    ];

    protected function casts(): array
    {
        return [
            'status' => TrustedDeviceStatusEnum::class,
            'sign_count' => 'integer',
            'approved_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lastUsedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_used_by');
    }

    public function isApproved(): bool
    {
        return $this->status === TrustedDeviceStatusEnum::APPROVED;
    }

    public function tokenName(): string
    {
        return self::TOKEN_PREFIX.$this->id;
    }

    /**
     * "Chrome · Windows" read from the browser's signature, for the list.
     */
    public function getBrowserAttribute(): ?string
    {
        $agent = (string) $this->user_agent;

        if ($agent === '') {
            return null;
        }

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'OPR/') => 'Opera',
            str_contains($agent, 'Firefox/') => 'Firefox',
            str_contains($agent, 'Chrome/') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Navigateur',
        };

        $system = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'iPhone'), str_contains($agent, 'iPad') => 'iOS',
            str_contains($agent, 'Mac OS') => 'macOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $system ? "{$browser} · {$system}" : $browser;
    }
}
