<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\AppointmentFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreLeadAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Lead;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadAppointmentController extends Controller
{
    public function __construct(protected AppointmentService $appointmentService) {}

    public function index(Request $request, Lead $lead, AppointmentFilter $filters): JsonResponse
    {
        $this->authorize('view', $lead);

        $perPage = (int) $request->integer('per_page', 15);

        $appointments = $this->appointmentService->paginateForLead($lead, $request->user(), $filters, $perPage);

        return $this->success(AppointmentResource::collection($appointments));
    }

    public function store(StoreLeadAppointmentRequest $request, Lead $lead): JsonResponse
    {
        $this->authorize('create', Appointment::class);

        $data = array_merge($request->validated(), ['lead_id' => $lead->id]);

        $appointment = $this->appointmentService->createAppointment($data, $request->user());

        $appointment->load(['lead', 'agent', 'creator']);

        return $this->created(new AppointmentResource($appointment), 'Appointment scheduled successfully');
    }
}
