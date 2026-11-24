<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * An address (or a range) refused by the whole CRM. The list is cached and
 * refreshed whenever it changes.
 */
class BlockedIp extends Model
{
    use LogsActivity;

    /** « Journal d'activité » */
    protected string $activityCategory = 'auth';

    /** @var array<int, string> */
    protected array $activityIgnore = ['blocked_attempts', 'last_attempt_at'];

    protected const CACHE_KEY = 'blocked_ips.list';

    protected $fillable = ['ip', 'note', 'created_by', 'blocked_attempts', 'last_attempt_at'];

    protected function casts(): array
    {
        return ['last_attempt_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The blocked entry matching this address, if any */
    public static function matching(?string $ip): ?string
    {
        if (! $ip) {
            return null;
        }

        $list = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()->pluck('ip')->all());
        foreach ($list as $entry) {
            if (IpUtils::checkIp($ip, $entry)) {
                return $entry;
            }
        }

        return null;
    }

    protected function activityAction(string $event, array $changes): string
    {
        return match ($event) {
            'created' => 'auth.ip_blocked_added',
            'deleted' => 'auth.ip_unblocked',
            default => 'auth.ip_block_updated',
        };
    }
}
