<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AppointmentStatusEnum;
use App\Filters\AppointmentFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\AppointmentStatisticsRequest;
use App\Http\Requests\Appointment\RescheduleAppointmentRequest;
use App\Http\Requests\Appointment\StoreAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentRequest;
use App\Http\Requests\Appointment\UpdateAppointmentStatusRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function __construct(protected AppointmentService $appointmentService) {}

    public function index(Request $request, AppointmentFilter $filters): JsonResponse
    {
        $this->authorize('viewAny', Appointment::class);
	//test test

        $perPage = (int) $request->integer('per_page', 15);

        $appointments = $this->appointmentService->paginateForUser($request->user(), $filters, $perPage);

        return $this->success(AppointmentResource::collection($appointments));
    }

    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $this->authorize('create', Appointment::class);

        $appointment = $this->appointmentService->createAppointment($request->validated(), $request->user());

        $appointment->load(['lead', 'agent', 'creator']);

        return $this->created(new AppointmentResource($appointment), 'Appointment scheduled successfully');
    }

    public function show(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('view', $appointment);

        $appointment->load(['lead', 'agent', 'creator']);

        return $this->success(new AppointmentResource($appointment));
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('update', $appointment);

        $appointment = $this->appointmentService->updateAppointment($appointment, $request->validated());

        $appointment->load(['lead', 'agent', 'creator']);

        return $this->success(new AppointmentResource($appointment), 'Appointment updated successfully');
    }

    public function destroy(Appointment $appointment): JsonResponse
    {
        $this->authorize('delete', $appointment);

        $this->appointmentService->deleteAppointment($appointment);

        return $this->noContent('Appointment deleted successfully');
    }

    public function reschedule(RescheduleAppointmentRequest $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('reschedule', $appointment);

        $appointment = $this->appointmentService->reschedule($appointment, $request->validated('scheduled_at'));

        $appointment->load(['lead', 'agent', 'creator']);

        return $this->success(new AppointmentResource($appointment), 'Appointment rescheduled successfully');
    }

    public function updateStatus(UpdateAppointmentStatusRequest $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('updateStatus', $appointment);

        $status = AppointmentStatusEnum::from($request->validated('status'));

        $appointment = $this->appointmentService->updateStatus($appointment, $status);

        $appointment->load(['lead', 'agent', 'creator']);

        return $this->success(new AppointmentResource($appointment), 'Appointment status updated successfully');
    }

    public function statistics(AppointmentStatisticsRequest $request): JsonResponse
    {
        $this->authorize('viewStatistics', Appointment::class);

        $stats = $this->appointmentService->statistics(
            $request->user(),
            $request->validated('from'),
            $request->validated('to'),
        );

        return $this->success($stats);
    }
}
