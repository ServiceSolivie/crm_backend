<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Filters\AppointmentReminderFilter;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Repositories\Contracts\AppointmentReminderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AppointmentReminderService
{
    public function __construct(protected AppointmentReminderRepositoryInterface $reminders) {}

    public function paginateForAppointment(Appointment $appointment, AppointmentReminderFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->reminders->paginateForAppointment($appointment->id, $filters, $perPage);
    }

    public function createReminder(Appointment $appointment, array $attributes): AppointmentReminder
    {
        $attributes['appointment_id'] = $appointment->id;

        return $this->reminders->create($attributes);
    }

    public function updateReminder(AppointmentReminder $reminder, array $attributes): AppointmentReminder
    {
        $reminder->update($attributes);

        return $reminder->refresh();
    }

    public function deleteReminder(AppointmentReminder $reminder): bool
    {
        return (bool) $reminder->delete();
    }

    public function markSent(AppointmentReminder $reminder): AppointmentReminder
    {
        if ($reminder->sent_at !== null) {
            throw new ApiException('This reminder has already been sent.', 409);
        }

        $reminder->update(['sent_at' => now()]);

        return $reminder->refresh();
    }
}
