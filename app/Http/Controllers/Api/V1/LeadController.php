<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LeadStatusEnum;
use App\Filters\LeadFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\AssignLeadRequest;
use App\Http\Requests\Lead\StoreLeadNoteRequest;
use App\Http\Requests\Lead\StoreLeadRequest;
use App\Http\Requests\Lead\UpdateLeadRequest;
use App\Http\Requests\Lead\UpdateLeadStatusRequest;
use App\Http\Resources\LeadAssignmentHistoryResource;
use App\Http\Resources\LeadNoteResource;
use App\Http\Resources\LeadResource;
use App\Http\Resources\LeadStatusHistoryResource;
use App\Models\Lead;
use App\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeadController extends Controller
{
    public function __construct(protected LeadService $leadService) {}

    public function index(Request $request, LeadFilter $filters): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);
        Log::info('Listing leads');
        Log::info('filters ', $request->all());
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

        $lead->load(['leadSource', 'assignedAgent', 'team', 'creator']);

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
