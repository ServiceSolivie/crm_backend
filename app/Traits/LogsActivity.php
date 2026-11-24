<?php

namespace App\Traits;

use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Records creations, changes (field by field, before → after), deletions and
 * restorations of a model in the « Journal d'activité ». A model can set
 * $activityCategory, $activityIgnore, $activityRefs (column => model whose
 * name is shown) and override activitySubject(), activityAction() or
 * activityValue().
 */
trait LogsActivity
{
    /** Turned off around mass operations that log a single summary line */
    public static bool $logActivity = true;

    /** @var array<string, string|null> */
    protected static array $activityNames = [];

    public static function bootLogsActivity(): void
    {
        static::created(fn (Model $model) => $model->recordActivity('created'));
        static::updated(fn (Model $model) => $model->recordActivity('updated'));
        static::deleted(fn (Model $model) => $model->recordActivity('deleted'));

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::restored(fn (Model $model) => $model->recordActivity('restored'));
        }
    }

    protected function recordActivity(string $event): void
    {
        if (! static::$logActivity) {
            return;
        }

        // The log never blocks the change it describes
        try {
            $this->writeActivity($event);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    protected function writeActivity(string $event): void
    {

        $changes = [];
        if ($event === 'updated') {
            $ignore = [...$this->activityIgnoredColumns(), $this->getUpdatedAtColumn(), $this->getCreatedAtColumn()];
            foreach (array_keys($this->getChanges()) as $key) {
                if (in_array($key, $ignore, true)) {
                    continue;
                }
                $before = $this->activityValue($key, $this->getRawOriginal($key));
                $after = $this->activityValue($key, $this->getAttributes()[$key] ?? null);
                if ($before !== $after) {
                    $changes[$key] = [$before, $after];
                }
            }
            if ($changes === []) {
                return;
            }
        }

        $subject = $this->activitySubject();
        $names = ['LeadNote' => 'Note', 'Payment' => 'Paiement', 'Appointment' => 'Rendez-vous', 'LeadDocument' => 'Document', 'Contract' => 'Contrat'];
        $self = class_basename($this);
        $description = $subject !== $this ? ($names[$self] ?? $self).' : '.ActivityLogger::label($this) : null;

        ActivityLogger::log(
            $this->activityCategory(),
            $this->activityAction($event, $changes),
            $subject,
            $this->activityDescription($event) ?? $description,
            $changes,
        );
    }

    protected function activityCategory(): string
    {
        return property_exists($this, 'activityCategory') ? $this->activityCategory : 'settings';
    }

    /** The record the line is shown on (a note is logged on its lead) */
    protected function activitySubject(): ?Model
    {
        return $this;
    }

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes
     */
    protected function activityAction(string $event, array $changes): string
    {
        return Str::snake(class_basename($this)).'.'.$event;
    }

    protected function activityDescription(string $event): ?string
    {
        return null;
    }

    /** @return array<int, string> */
    protected function activityIgnoredColumns(): array
    {
        return [...(property_exists($this, 'activityIgnore') ? $this->activityIgnore : []), 'password', 'remember_token'];
    }

    /** A value as shown in the log: names instead of ids, Oui / Non, dates */
    protected function activityValue(string $key, mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $refs = property_exists($this, 'activityRefs') ? $this->activityRefs : [];
        if (isset($refs[$key])) {
            $class = $refs[$key];
            $cacheKey = $class.'#'.$value;
            if (! array_key_exists($cacheKey, static::$activityNames)) {
                $record = $class::query()->find($value);
                static::$activityNames[$cacheKey] = $record ? ($record instanceof User ? $record->name : ActivityLogger::label($record)) : "#{$value}";
            }

            return static::$activityNames[$cacheKey];
        }

        if (in_array($this->getCasts()[$key] ?? null, ['bool', 'boolean'], true)) {
            return (bool) $value ? 'Oui' : 'Non';
        }

        return is_string($value) ? Str::limit($value, 300, '…') : $value;
    }
}
