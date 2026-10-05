<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\CallFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Call\AssignCallLeadRequest;
use App\Http\Requests\Call\InitiateCallRequest;
use App\Http\Requests\Call\LinkRingoverCallRequest;
use App\Http\Requests\Call\UpdateCallRequest;
use App\Http\Resources\CallResource;
use App\Models\Call;
use App\Models\Lead;
use App\Services\CallService;
use App\Services\Ringover\RingoverClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CallController extends Controller
{
    public function __construct(
        protected CallService $callService,
        protected RingoverClient $ringover,
    ) {}

    public function index(Request $request, CallFilter $filters): JsonResponse
    {
        $this->authorize('viewAny', Call::class);

        $calls = $this->callService->paginateForUser($request->user(), $filters, $this->perPage($request));

        return $this->success(CallResource::collection($calls));
    }

    public function forLead(Request $request, Lead $lead, CallFilter $filters): JsonResponse
    {
        $this->authorize('view', $lead);
        $this->authorize('viewAny', Call::class);

        $calls = $this->callService->paginateForLead($lead, $request->user(), $filters, $this->perPage($request));

        return $this->success(CallResource::collection($calls));
    }

    public function show(Call $call): JsonResponse
    {
        $call->load(['lead', 'agent']);

        $this->authorize('view', $call);

        return $this->success(new CallResource($call));
    }

    /**
     * Start a call to a lead from the CRM. Returns the call and, for the
     * embedded phone, the numbers it must dial ("mobile": Ringover rings the
     * agent's devices itself, nothing to dial).
     */
    public function initiate(InitiateCallRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);
        $this->authorize('create', Call::class);

        $viaMobile = $request->validated('via') === 'mobile';
        $call = $this->callService->initiate($lead, $request->user(), $viaMobile);

        return $this->created([
            'call' => new CallResource($call->load(['lead', 'agent'])),
            'dial' => $viaMobile ? null : [
                'to' => $call->to_number,
                'from' => $call->from_number,
            ],
        ], $viaMobile ? 'Ringover fait sonner votre téléphone' : 'Appel initié');
    }

    public function assignLead(AssignCallLeadRequest $request, Call $call): JsonResponse
    {
        $call->load('lead');
        $this->authorize('assignLead', $call);

        $lead = Lead::findOrFail($request->validated('lead_id'));
        $this->authorize('view', $lead);

        $call = $this->callService->assignLead($call, $lead);

        return $this->success(new CallResource($call->load(['lead', 'agent'])), 'Appel associé au lead');
    }

    public function recording(Call $call): Response
    {
        return $this->media($call, $call->recording_url, 'enregistrement');
    }

    public function voicemail(Call $call): Response
    {
        return $this->media($call, $call->voicemail_url, 'messagerie');
    }

    public function transcription(Call $call): Response
    {
        return $this->media($call, $call->transcription_url, 'transcription');
    }

    /**
     * Serve a Ringover file through the CRM, so the permission is checked on
     * every play and Ringover links never reach the browser.
     */
    protected function media(Call $call, ?string $url, string $name): Response
    {
        $call->load('lead');
        $this->authorize('listen', $call);

        if ($url === null) {
            return $this->error('Aucun fichier disponible pour cet appel.', 404);
        }

        $upstream = $this->ringover->fetchMedia($url);
        $type = $upstream->header('Content-Type') ?: 'application/octet-stream';
        $extension = str_contains($type, 'audio') ? 'mp3' : (str_contains($type, 'json') ? 'json' : 'txt');

        return response($upstream->body(), 200, [
            'Content-Type' => $type,
            'Content-Disposition' => "inline; filename=\"appel-{$call->id}-{$name}.{$extension}\"",
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function linkRingover(LinkRingoverCallRequest $request, Call $call): JsonResponse
    {
        $this->authorize('update', $call);

        $call = $this->callService->linkRingoverCall($call, $request->validated('ringover_call_id'));

        return $this->success(new CallResource($call->load(['lead', 'agent'])));
    }

    public function update(UpdateCallRequest $request, Call $call): JsonResponse
    {
        $this->authorize('update', $call);

        $call = $this->callService->updateNote($call, $request->validated('note'));

        return $this->success(new CallResource($call->load(['lead', 'agent'])), 'Note enregistrée');
    }

    protected function perPage(Request $request): int
    {
        return min(100, max(1, $request->integer('per_page', 15)));
    }
}
