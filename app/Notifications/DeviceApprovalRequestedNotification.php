<?php

namespace App\Notifications;

use App\Models\TrustedDevice;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

/**
 * A machine asked to be approved: sent to the super admins.
 */
class DeviceApprovalRequestedNotification extends Notification
{
    public function __construct(protected TrustedDevice $device) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Own `devices` payload key, so the bell's click-through goes to the
     * machines page.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Nouveau poste à approuver',
            'devices' => [[
                'id' => $this->device->id,
                'requested_by' => $this->device->registeredBy?->name,
                'browser' => $this->device->browser,
            ]],
        ];
    }

    /**
     * Broadcast on the 'sync' connection so it fires immediately (no
     * persistent queue worker on this app's hosting).
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))->onConnection('sync');
    }
}
