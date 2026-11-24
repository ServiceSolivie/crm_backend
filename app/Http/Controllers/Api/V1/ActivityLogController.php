<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * « Journal d'activité » (super admin only):
 *   GET /activity-logs           lines, newest first, filtered
 *   GET /activity-logs/summary   today's headline figures
 *   GET /activity-logs/export    the filtered lines as CSV
 */
class ActivityLogController extends Controller
{
    public const CATEGORIES = ['auth', 'lead', 'payment', 'user', 'export', 'settings'];

    /** Actions shown as « sensibles » */
    protected const SENSITIVE = [
        'auth.login_blocked', 'auth.password_reset', 'user.deleted', 'lead.deleted', 'payment.deleted',
        'export.imported_leads', 'team.deleted', 'lead_status.deleted', 'lead_source.deleted',
    ];

    public function index(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        $logs = $this->filtered($request)->orderByDesc('id')->paginate(min(100, max(10, $request->integer('per_page', 50))));

        return $this->success([
            'data' => collect($logs->items())->map(fn (ActivityLog $log) => $this->row($log))->all(),
            'meta' => ['current_page' => $logs->currentPage(), 'last_page' => $logs->lastPage(), 'total' => $logs->total(), 'per_page' => $logs->perPage()],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $this->authorizeView($request);
        $today = ActivityLog::query()->where('created_at', '>=', now()->startOfDay());

        return $this->success([
            'logins_today' => (clone $today)->where('action', 'auth.login')->count(),
            'failed_today' => (clone $today)->whereIn('action', ['auth.login_failed', 'auth.login_blocked', 'auth.device_refused'])->count(),
            'lead_actions_today' => (clone $today)->where('category', 'lead')->count(),
            'sensitive_today' => (clone $today)->whereIn('action', self::SENSITIVE)->count(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorizeView($request);
        $query = $this->filtered($request)->orderByDesc('id')->limit(20000);

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Utilisateur', 'Catégorie', 'Action', 'Sur', 'Détail', 'Changements', 'IP', 'Navigateur'], ';');
            foreach ($query->cursor() as $log) {
                fputcsv($out, [
                    $log->created_at->timezone('Europe/Paris')->format('d/m/Y H:i:s'),
                    $log->user_name,
                    $log->category,
                    $log->action,
                    $log->subject_label,
                    $log->description,
                    collect($log->changes ?? [])->map(fn ($c, $k) => "{$k}: ".json_encode($c[0], JSON_UNESCAPED_UNICODE).' → '.json_encode($c[1], JSON_UNESCAPED_UNICODE))->implode(' | '),
                    $log->ip,
                    $log->user_agent,
                ], ';');
            }
            fclose($out);
        }, 'journal-activite-'.now()->format('Y-m-d_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function authorizeView(Request $request): void
    {
        abort_unless($request->user()?->hasRole(RoleEnum::SUPER_ADMIN->value), 403, 'Réservé au super admin.');
    }

    protected function filtered(Request $request): Builder
    {
        $query = ActivityLog::query();

        if (in_array($request->query('category'), self::CATEGORIES, true)) {
            $query->where('category', $request->query('category'));
        }
        if ($request->query('sensitive')) {
            $query->whereIn('action', self::SENSITIVE);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }
        if ($request->filled('action')) {
            $query->where('action', (string) $request->query('action'));
        }
        if ($request->filled('subject_type') && $request->filled('subject_id')) {
            $query->where('subject_type', (string) $request->query('subject_type'))->where('subject_id', $request->integer('subject_id'));
        }
        if ($from = $this->date($request->query('from'))) {
            $query->where('created_at', '>=', $from->startOfDay()->utc());
        }
        if ($to = $this->date($request->query('to'))) {
            $query->where('created_at', '<=', $to->endOfDay()->utc());
        }
        if ($search = trim((string) $request->query('search'))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(fn (Builder $q) => $q->where('subject_label', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('user_name', 'like', $like)
                ->orWhere('ip', 'like', $like)
                ->orWhere('meta', 'like', $like));
        }

        return $query;
    }

    protected function date(mixed $value): ?CarbonImmutable
    {
        try {
            return $value ? CarbonImmutable::parse((string) $value, 'Europe/Paris') : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(ActivityLog $log): array
    {
        return [
            'id' => $log->id,
            'at' => $log->created_at?->toIso8601String(),
            'user_id' => $log->user_id,
            'user_name' => $log->user_name,
            'category' => $log->category,
            'action' => $log->action,
            'sensitive' => in_array($log->action, self::SENSITIVE, true),
            'subject_type' => $log->subject_type,
            'subject_id' => $log->subject_id,
            'subject_label' => $log->subject_label,
            'description' => $log->description,
            'changes' => $log->changes,
            'meta' => $log->meta,
            'ip' => $log->ip,
            'user_agent' => $log->user_agent,
        ];
    }
}
