<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class LeadReceivedNotification extends Notification
{
    public function __construct(protected Lead $lead) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Own `leads` payload key (not `appointments`) — a different shape on
     * purpose, so the bell's click-through goes to /leads/:id instead of
     * /appointments/:id.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'You received a new lead',
            'leads' => [[
                'id' => $this->lead->id,
                'name' => trim(($this->lead->first_name ?? '').' '.($this->lead->last_name ?? '')) ?: null,
                'phone' => $this->lead->phone,
                'source' => $this->lead->leadSource?->name,
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
