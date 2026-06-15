<?php

namespace App\Repositories\Eloquent;

use App\Filters\AppointmentReminderFilter;
use App\Models\AppointmentReminder;
use App\Repositories\Contracts\AppointmentReminderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AppointmentReminderRepository extends BaseRepository implements AppointmentReminderRepositoryInterface
{
    public function model(): string
    {
        return AppointmentReminder::class;
    }

    public function paginateForAppointment(int $appointmentId, AppointmentReminderFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->newQuery()
            ->where('appointment_id', $appointmentId)
            ->filter($filters)
            ->paginate($perPage);
    }
}
