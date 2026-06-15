<?php

namespace App\Repositories\Contracts;

use App\Filters\AppointmentReminderFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface AppointmentReminderRepositoryInterface extends RepositoryInterface
{
    public function paginateForAppointment(int $appointmentId, AppointmentReminderFilter $filters, int $perPage = 15): LengthAwarePaginator;
}
