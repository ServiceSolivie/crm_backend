<?php

namespace App\Policies;

use App\Enums\PermissionEnum;
use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    /**
     * Determine whether the user can view the appointments list at all.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionEnum::APPOINTMENTS_VIEW_ALL->value)
            || $user->can(PermissionEnum::APPOINTMENTS_VIEW_TEAM->value)
            || $user->can(PermissionEnum::APPOINTMENTS_VIEW_OWN->value);
    }

    public function view(User $user, Appointment $appointment): bool
    {
        return $this->canAccess($user, $appointment);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionEnum::APPOINTMENTS_CREATE->value);
    }

    public function update(User $user, Appointment $appointment): bool
    {
        return $user->can(PermissionEnum::APPOINTMENTS_UPDATE->value) && $this->canAccess($user, $appointment);
    }

    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->can(PermissionEnum::APPOINTMENTS_DELETE->value) && $this->canAccess($user, $appointment);
    }

    public function reschedule(User $user, Appointment $appointment): bool
    {
        return $user->can(PermissionEnum::APPOINTMENTS_UPDATE->value) && $this->canAccess($user, $appointment);
    }

    public function updateStatus(User $user, Appointment $appointment): bool
    {
        return $user->can(PermissionEnum::APPOINTMENTS_UPDATE->value) && $this->canAccess($user, $appointment);
    }

    /**
     * Statistics visibility mirrors list visibility.
     */
    public function viewStatistics(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Managing reminders follows the same rules as updating the appointment.
     */
    public function manageReminders(User $user, Appointment $appointment): bool
    {
        return $user->can(PermissionEnum::APPOINTMENTS_UPDATE->value) && $this->canAccess($user, $appointment);
    }

    /**
     * A user may access an appointment if they can see all appointments,
     * see their team's appointments and the agent belongs to their team,
     * or see their own appointments and they are the assigned agent.
     */
    protected function canAccess(User $user, Appointment $appointment): bool
    {
        if ($user->can(PermissionEnum::APPOINTMENTS_VIEW_ALL->value)) {
            return true;
        }

        if ($user->can(PermissionEnum::APPOINTMENTS_VIEW_TEAM->value)
            && $user->team_id !== null
            && $appointment->agent?->team_id === $user->team_id) {
            return true;
        }

        if ($user->can(PermissionEnum::APPOINTMENTS_VIEW_OWN->value) && $appointment->agent_id === $user->id) {
            return true;
        }

        return false;
    }
}
