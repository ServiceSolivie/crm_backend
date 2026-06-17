<?php

namespace App\Services;

use App\Enums\AppointmentStatusEnum;
use App\Enums\PermissionEnum;
use App\Exceptions\ApiException;
use App\Filters\AppointmentFilter;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\User;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class AppointmentService extends BaseService
{
    /**
     * Statuses that can no longer be changed once set.
     *
     * @var array<int, string>
     */
    protected const TERMINAL_STATUSES = [
        AppointmentStatusEnum::REALISE->value,
        AppointmentStatusEnum::ANNULE->value,
        AppointmentStatusEnum::NON_VENU->value,
    ];

    public function __construct(protected AppointmentRepositoryInterface $appointments)
    {
        parent::__construct($appointments);
    }

    /**
     * Paginate appointments, scoped to what the given user is allowed to see.
     */
    public function paginateForUser(User $user, AppointmentFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->appointments->paginateFiltered($filters, $perPage, $this->visibilityScope($user));
    }

    /**
     * Paginate appointments belonging to a specific lead, scoped to what the user may see.
     */
    public function paginateForLead(Lead $lead, User $user, AppointmentFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->appointments->paginateFiltered($filters, $perPage, function (Builder $query) use ($lead, $user) {
            $query->where('lead_id', $lead->id);
            ($this->visibilityScope($user))($query);
        });
    }

    /**
     * Restrict the appointment query according to the user's view permissions.
     * Team and personal scopes are anchored to the lead, not the agent.
     *
     * This is the single source of truth for appointment visibility and is
     * also reused by DashboardService to keep dashboard widgets consistent
     * with the appointments list/statistics endpoints.
     */
    public function visibilityScope(User $user): \Closure
    {
        return function (Builder $query) use ($user) {
            if ($user->can(PermissionEnum::APPOINTMENTS_VIEW_ALL->value)) {
                return;
            }

            if ($user->can(PermissionEnum::APPOINTMENTS_VIEW_TEAM->value)) {
                $query->whereHas('lead', fn (Builder $leads) => $leads->where('team_id', $user->team_id));

                return;
            }

            if ($user->can(PermissionEnum::APPOINTMENTS_VIEW_OWN->value)) {
                $query->where('agent_id', $user->id);

                return;
            }

            $query->whereRaw('1 = 0');
        };
    }

    /**
     * Schedule a new appointment for the given lead, guarding against double-booking the agent.
     */
    public function createAppointment(array $data, User $creator): Appointment
    {
        $data['created_by'] = $creator->id;
        $data['status'] = $data['status'] ?? AppointmentStatusEnum::PLANIFIE->value;

        $this->guardAgainstConflict((int) $data['agent_id'], $data['scheduled_at']);

        /** @var Appointment $appointment */
        $appointment = $this->appointments->create($data);

        return $appointment->refresh();
    }

    /**
     * Update appointment details. lead_id, status and scheduled_at cannot be
     * changed through this method — use reschedule()/updateStatus() instead.
     */
    public function updateAppointment(Appointment $appointment, array $data): Appointment
    {
        unset($data['lead_id'], $data['status'], $data['scheduled_at'], $data['created_by']);

        if (isset($data['agent_id']) && (int) $data['agent_id'] !== $appointment->agent_id) {
            $this->guardAgainstConflict((int) $data['agent_id'], (string) $appointment->scheduled_at);
        }

        $appointment->update($data);

        return $appointment->refresh();
    }

    public function deleteAppointment(Appointment $appointment): bool
    {
        return $appointment->delete();
    }

    /**
     * Move an appointment to a new date/time and mark it as rescheduled.
     */
    public function reschedule(Appointment $appointment, string $scheduledAt): Appointment
    {
        if (in_array($appointment->status->value, self::TERMINAL_STATUSES, true)) {
            throw new ApiException('Cannot reschedule a completed, cancelled, or no-show appointment.', 422);
        }

        $this->guardAgainstConflict($appointment->agent_id, $scheduledAt, $appointment->id);

        $appointment->update([
            'scheduled_at' => $scheduledAt,
            'status' => AppointmentStatusEnum::REPORTE->value,
        ]);

        return $appointment->refresh();
    }

    /**
     * Move an appointment to a new status, guarding against changes to
     * terminal (completed/cancelled/no-show) appointments.
     */
    public function updateStatus(Appointment $appointment, AppointmentStatusEnum $status): Appointment
    {
        if (in_array($appointment->status->value, self::TERMINAL_STATUSES, true) && $appointment->status !== $status) {
            throw new ApiException('Cannot change the status of a completed, cancelled, or no-show appointment.', 422);
        }

        $appointment->update(['status' => $status->value]);

        return $appointment->refresh();
    }

    /**
     * Aggregate appointment statistics, scoped to what the given user is allowed to see.
     *
     * @return array<string, mixed>
     */
    public function statistics(User $user, ?string $from = null, ?string $to = null): array
    {
        return $this->appointments->statistics($this->visibilityScope($user), $from, $to);
    }

    /**
     * @throws ApiException if the agent already has a planned appointment at this time.
     */
    protected function guardAgainstConflict(int $agentId, string $scheduledAt, ?int $excludeId = null): void
    {
        if ($this->appointments->hasConflict($agentId, $scheduledAt, $excludeId)) {
            throw new ApiException('This agent already has an appointment scheduled at this time.', 409);
        }
    }
}
