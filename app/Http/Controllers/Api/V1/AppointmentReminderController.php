<?php

namespace App\Http\Controllers\Api\V1;

use App\Filters\AppointmentReminderFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentReminder\StoreAppointmentReminderRequest;
use App\Http\Requests\AppointmentReminder\UpdateAppointmentReminderRequest;
use App\Http\Resources\AppointmentReminderResource;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Services\AppointmentReminderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentReminderController extends Controller
{
    public function __construct(protected AppointmentReminderService $reminderService) {}

    public function index(Request $request, Appointment $appointment, AppointmentReminderFilter $filters): JsonResponse
    {
        $this->authorize('manageReminders', $appointment);

        $perPage = (int) $request->integer('per_page', 15);

        $reminders = $this->reminderService->paginateForAppointment($appointment, $filters, $perPage);

        return $this->success(AppointmentReminderResource::collection($reminders));
    }

    public function store(StoreAppointmentReminderRequest $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('manageReminders', $appointment);

        $reminder = $this->reminderService->createReminder($appointment, $request->validated());

        return $this->created(new AppointmentReminderResource($reminder), 'Reminder created successfully');
    }

    public function update(UpdateAppointmentReminderRequest $request, Appointment $appointment, AppointmentReminder $reminder): JsonResponse
    {
        $this->authorize('manageReminders', $appointment);

        $reminder = $this->reminderService->updateReminder($reminder, $request->validated());

        return $this->success(new AppointmentReminderResource($reminder), 'Reminder updated successfully');
    }

    public function destroy(Appointment $appointment, AppointmentReminder $reminder): JsonResponse
    {
        $this->authorize('manageReminders', $appointment);

        $this->reminderService->deleteReminder($reminder);

        return $this->noContent('Reminder deleted successfully');
    }

    public function markSent(Appointment $appointment, AppointmentReminder $reminder): JsonResponse
    {
        $this->authorize('manageReminders', $appointment);

        $reminder = $this->reminderService->markSent($reminder);

        return $this->success(new AppointmentReminderResource($reminder), 'Reminder marked as sent');
    }
}
