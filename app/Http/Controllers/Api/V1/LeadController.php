<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LeadStatusEnum;
use App\Filters\LeadFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\AssignLeadRequest;
use App\Http\Requests\Lead\BulkLeadRequest;
use App\Http\Requests\Lead\CrossSellLeadRequest;
use App\Http\Requests\Lead\StoreLeadCallRequest;
use App\Http\Requests\Lead\StoreLeadNoteRequest;
use App\Http\Requests\Lead\StoreLeadRequest;
use App\Http\Requests\Lead\UpdateLeadRequest;
use App\Http\Requests\Lead\UpdateLeadStatusRequest;
use App\Http\Resources\LeadAssignmentHistoryResource;
use App\Http\Resources\LeadCallResource;
use App\Http\Resources\LeadNoteResource;
use App\Http\Resources\LeadResource;
use App\Http\Resources\LeadStatusHistoryResource;
use App\Models\Lead;
use App\Services\LeadService;
use App\Services\LeadWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function __construct(
        protected LeadService $leadService,
        protected LeadWorkspaceService $workspace,
    ) {}

    /**
     * Quick search for the command palette: GET /leads/search?q=…&limit=8
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $leads = $this->workspace->search($request->user(), $data['q'], (int) ($data['limit'] ?? 8));

        return $this->success($leads->map(fn (Lead $lead) => [
            'id' => $lead->id,
            'reference' => $lead->reference,
            'first_name' => $lead->first_name,
            'last_name' => $lead->last_name,
            'phone' => $lead->phone,
            'email' => $lead->email,
            'status' => $lead->status->value,
            'insurance_type' => $lead->insurance_type?->value,
            'assigned_agent' => $lead->assignedAgent ? ['id' => $lead->assignedAgent->id, 'name' => $lead->assignedAgent->name] : null,
        ])->values());
    }

    /**
     * Counts for the list's saved views: GET /leads/counts
     */
    public function counts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        return $this->success($this->workspace->counts($request->user()));
    }

    /**
     * Duplicate check while typing: GET /leads/duplicates?phone=…&email=…&exclude_id=…
     */
    public function duplicates(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'max:255'],
            'exclude_id' => ['nullable', 'integer'],
        ]);

        return $this->success($this->workspace->duplicates(
            $request->user(),
            $data['phone'] ?? null,
            $data['email'] ?? null,
            isset($data['exclude_id']) ? (int) $data['exclude_id'] : null,
        ));
    }

    /**
     * One action on several leads: POST /leads/bulk
     */
    public function bulk(BulkLeadRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);

        $result = $this->leadService->bulk(
            $request->user(),
            array_map('intval', $request->validated('ids')),
            $request->validated('action'),
            $request->validated(),
        );

        return $this->success($result, "{$result['done']} lead(s) traité(s)");
    }

    /**
     * Unified, paginated timeline: GET /leads/{lead}/activity?page=…
     */
    public function activity(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);

        $items = $this->workspace->activity(
            $lead,
            $request->user(),
            max(1, (int) $request->integer('page', 1)),
            min(100, max(1, (int) $request->integer('per_page', 30))),
            $request->validate(['type' => ['nullable', 'in:note,call,status,assignment,appointment,payment,document']])['type'] ?? null,
        );

        // Plain arrays in the standard { data, meta, links } envelope
        return $this->success(JsonResource::collection($items));
    }

    /**
     * Previous / next lead with the list's filters: GET /leads/{lead}/neighbours?…
     */
    public function neighbours(Request $request, Lead $lead, LeadFilter $filters): JsonResponse
    {
        $this->authorize('view', $lead);

        return $this->success($this->workspace->neighbours($lead, $request->user(), $filters));
    }

    public function index(Request $request, LeadFilter $filters): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);
        $perPage = (int) $request->integer('per_page', 15);

        $leads = $this->leadService->paginateForUser($request->user(), $filters, $perPage);

        return $this->success(LeadResource::collection($leads));
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        $this->authorize('create', Lead::class);

        $lead = $this->leadService->createLead($request->validated(), $request->user());

        return $this->created(new LeadResource($lead), 'Lead created successfully');
    }

    public function show(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('view', $lead);

        $lead->load(['leadSource', 'assignedAgent', 'team', 'creator', 'doublonOf', 'nextAppointment', 'lastFlag.changedBy'])->loadCount('calls');

        return $this->success(new LeadResource($lead));
    }

    public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('update', $lead);

        $lead = $this->leadService->updateLead($lead, $request->validated());

        return $this->success(new LeadResource($lead), 'Lead updated successfully');
    }

    public function destroy(Lead $lead): JsonResponse
    {
        $this->authorize('delete', $lead);

        $this->leadService->deleteLead($lead);

        return $this->noContent('Lead deleted successfully');
    }

    public function assign(AssignLeadRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('assign', $lead);

        $lead = $this->leadService->assign($lead, (int) $request->validated('assigned_to'), $request->user());

        return $this->success(new LeadResource($lead), 'Lead assigned successfully');
    }

    public function crossSell(CrossSellLeadRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('crossSell', $lead);

        $newLead = $this->leadService->createCrossSell(
            $lead,
            $request->validated('insurance_type'),
            $request->user(),
            $request->validated('client_type'),
            $request->validated('comment'),
        );

        return $this->created(new LeadResource($newLead), 'Lead créé pour le nouveau produit');
    }

    public function updateStatus(UpdateLeadStatusRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('updateStatus', $lead);

        $status = LeadStatusEnum::from($request->validated('status'));

        $lead = $this->leadService->updateStatus(
            $lead,
            $status,
            $request->user(),
            $request->validated('comment'),
            $request->validated('expected_revenue'),
        );

        return $this->success(new LeadResource($lead), 'Statut du lead mis à jour avec succès');
    }

    public function notes(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('manageNotes', $lead);

        $notes = $this->leadService->notes($lead, (int) $request->integer('per_page', 15));

        return $this->success(LeadNoteResource::collection($notes));
    }

    public function storeNote(StoreLeadNoteRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('manageNotes', $lead);

        $note = $this->leadService->addNote($lead, $request->user(), $request->validated('note'));

        $note->load('user');

        return $this->created(new LeadNoteResource($note), 'Note added successfully');
    }

    public function calls(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('manageCalls', $lead);

        $calls = $this->leadService->calls($lead, (int) $request->integer('per_page', 15));

        return $this->success(LeadCallResource::collection($calls));
    }

    public function storeCall(StoreLeadCallRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('manageCalls', $lead);

        $call = $this->leadService->logCall(
            $lead,
            $request->user(),
            $request->validated('outcome'),
            $request->validated('note'),
        );

        $call->load('user');

        return $this->created(new LeadCallResource($call), 'Call logged successfully');
    }

    public function statusHistory(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('viewHistory', $lead);

        $history = $this->leadService->statusHistory($lead, (int) $request->integer('per_page', 15));

        return $this->success(LeadStatusHistoryResource::collection($history));
    }

    public function assignmentHistory(Request $request, Lead $lead): JsonResponse
    {
        $this->authorize('viewHistory', $lead);

        $history = $this->leadService->assignmentHistory($lead, (int) $request->integer('per_page', 15));

        return $this->success(LeadAssignmentHistoryResource::collection($history));
    }
}
