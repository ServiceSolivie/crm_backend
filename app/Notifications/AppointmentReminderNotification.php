<?php

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class AppointmentReminderNotification extends Notification
{
    public function __construct(protected Appointment $appointment) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Same `{ title, appointments }` shape as TodayAppointmentsNotification
     * (even though there's only ever one appointment here) so the bell's
     * existing template renders both notification types with no changes.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Appointment in 30 minutes',
            'appointments' => [[
                'id' => $this->appointment->id,
                'lead_name' => trim(
                    ($this->appointment->lead?->first_name ?? '').' '.($this->appointment->lead?->last_name ?? '')
                ) ?: null,
                'scheduled_at' => $this->appointment->scheduled_at?->toIso8601String(),
                'location' => $this->appointment->location,
            ]],
        ];
    }

    /**
     * Broadcast on the 'sync' connection so it fires immediately — no
     * persistent `queue:work` process on this app's hosting, so a normally
     * queued BroadcastEvent would sit in the `jobs` table and never reach
     * Pusher.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))->onConnection('sync');
    }
}
