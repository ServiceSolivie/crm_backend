<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IntakeRequest;
use App\Services\IntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Super admin side of the marketing intake: the address and key to give
 * out, and the requests received (GET/DELETE /intake/requests...).
 */
class IntakeRequestController extends Controller
{
    public function __construct(protected IntakeService $intake) {}

    public function settings(): JsonResponse
    {
        $this->authorize('manage', IntakeRequest::class);

        return $this->success([
            'url' => url('/api/v1/intake/leads'),
            'key' => $this->intake->key(),
            'retention_days' => IntakeService::RETENTION_DAYS,
        ]);
    }

    public function regenerateKey(): JsonResponse
    {
        $this->authorize('manage', IntakeRequest::class);

        return $this->success(['key' => $this->intake->regenerateKey()], 'Nouvelle clé générée');
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage', IntakeRequest::class);

        $this->intake->prune();
        $page = IntakeRequest::query()
            ->latest('received_at')
            ->latest('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 25))));

        return $this->success([
            'data' => collect($page->items())->map(fn (IntakeRequest $r) => [
                ...$this->outline($r),
                'preview' => $r->body !== null ? Str::limit(preg_replace('/\s+/', ' ', $r->body), 140) : null,
            ])->all(),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
            ],
        ]);
    }

    public function show(IntakeRequest $intakeRequest): JsonResponse
    {
        $this->authorize('manage', IntakeRequest::class);

        return $this->success([
            ...$this->outline($intakeRequest),
            'body' => $intakeRequest->body,
            'truncated' => $intakeRequest->body !== null && $intakeRequest->size > strlen($intakeRequest->body),
            'query' => $intakeRequest->query,
            'headers' => $intakeRequest->headers,
        ]);
    }

    public function destroy(IntakeRequest $intakeRequest): JsonResponse
    {
        $this->authorize('manage', IntakeRequest::class);

        $intakeRequest->delete();

        return $this->noContent('Requête supprimée');
    }

    public function destroyAll(): JsonResponse
    {
        $this->authorize('manage', IntakeRequest::class);

        IntakeRequest::query()->delete();

        return $this->noContent('Requêtes supprimées');
    }

    /**
     * @return array<string, mixed>
     */
    protected function outline(IntakeRequest $r): array
    {
        return [
            'id' => $r->id,
            'received_at' => $r->received_at?->toIso8601String(),
            'method' => $r->method,
            'content_type' => $r->content_type,
            'size' => $r->size,
            'ip' => $r->ip,
            'key_valid' => $r->key_valid,
            'key_via' => $r->key_via,
            'user_agent' => $r->headers['user-agent'] ?? null,
        ];
    }
}
