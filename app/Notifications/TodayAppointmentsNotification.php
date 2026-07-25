<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class TodayAppointmentsNotification extends Notification
{
    /**
     * @param  Collection<int, \App\Models\Appointment>  $appointments
     */
    public function __construct(protected Collection $appointments) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Stored in the `notifications` table and — because `via()` also
     * includes 'broadcast' — pushed live over Pusher on the recipient's
     * private `App.Models.User.{id}` channel using this same payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $count = $this->appointments->count();

        return [
            // 'title' is kept only for older clients/records; the frontend
            // renders its own localized title from `count` + notification
            // type, since this string is baked in English at creation time
            // and can never be retranslated later if stored as-is.
            'title' => $count === 1
                ? 'You have 1 appointment today'
                : "You have {$count} appointments today",
            'count' => $count,
            'appointments' => $this->appointments
                ->sortBy('scheduled_at')
                ->take(5)
                ->values()
                ->map(fn ($appointment) => [
                    'id' => $appointment->id,
                    'lead_name' => trim(
                        ($appointment->lead?->first_name ?? '').' '.($appointment->lead?->last_name ?? '')
                    ) ?: null,
                    'scheduled_at' => $appointment->scheduled_at?->toIso8601String(),
                    'location' => $appointment->location,
                ]),
        ];
    }

    /**
     * Broadcast the same payload, but on the 'sync' queue connection so it
     * fires immediately — there is no persistent `queue:work` process on
     * this app's hosting, so a normally-queued BroadcastEvent would sit in
     * the `jobs` table forever and never actually reach Pusher.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))->onConnection('sync');
    }
}
