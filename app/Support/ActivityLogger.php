<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\LeadDocument;
use App\Models\LeadNote;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Writes the « Journal d'activité »: who (the signed-in user, or « Système »
 * for webhooks and imports), what, on what, when and from where. Logging
 * never blocks the action it describes: a failure is only reported.
 */
class ActivityLogger
{
    /** Set while a background job / webhook acts, to name it in the log */
    protected static ?string $actorLabel = null;

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $changes  field => [before, after]
     * @param  array<string, mixed>  $meta
     */
    public static function log(
        string $category,
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        array $changes = [],
        array $meta = [],
        ?User $user = null,
    ): void {
        try {
            $user ??= auth()->user() instanceof User ? auth()->user() : null;
            $request = app()->runningInConsole() ? null : request();

            ActivityLog::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? static::$actorLabel ?? 'Système',
                'category' => $category,
                'action' => $action,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id' => $subject?->getKey(),
                'subject_label' => $subject ? Str::limit(static::label($subject), 250, '…') : null,
                'description' => $description,
                'changes' => $changes ?: null,
                'meta' => $meta ?: null,
                'ip' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 250, '') : null,
            ]);

            static::prune();
        } catch (\Throwable $e) {
            Log::warning('activity log failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Run $callback with every log line attributed to $label (« Google Sheet »…)
     * when no user is signed in.
     */
    public static function as(string $label, callable $callback): mixed
    {
        $previous = static::$actorLabel;
        static::$actorLabel = $label;
        try {
            return $callback();
        } finally {
            static::$actorLabel = $previous;
        }
    }

    /** How a record is named in the log */
    public static function label(Model $model): string
    {
        return match (true) {
            $model instanceof Lead => trim($model->reference.' · '.($model->company_name ?: trim($model->first_name.' '.$model->last_name))),
            $model instanceof User => "{$model->name} (#{$model->id})",
            $model instanceof Payment => number_format((float) $model->amount, 2, ',', ' ').' €'.($model->status ? ' · '.$model->status->label() : ''),
            $model instanceof LeadNote => '« '.Str::limit((string) $model->note, 80, '…').' »',
            $model instanceof LeadDocument => (string) $model->original_filename,
            $model instanceof Appointment => 'RDV du '.$model->scheduled_at?->timezone('Europe/Paris')->format('d/m/Y H:i'),
            default => (string) ($model->getAttribute('name') ?? $model->getAttribute('label') ?? $model->getAttribute('title') ?? $model->getAttribute('reference') ?? '#'.$model->getKey()),
        };
    }

    /** Lines older than the retention are removed now and then */
    protected static function prune(): void
    {
        if (random_int(1, 500) === 1) {
            ActivityLog::query()->where('created_at', '<', now()->subMonths(ActivityLog::RETENTION_MONTHS))->delete();
        }
    }
}
