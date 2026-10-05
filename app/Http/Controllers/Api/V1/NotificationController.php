<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The current user's in-app notifications (notification bell).
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->authorizedUser($request);

        $notifications = $user->notifications()->latest()->limit(min(50, max(1, $request->integer('limit', 20))))->get();

        return $this->success([
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $notifications->map(fn (DatabaseNotification $n) => [
                'id' => $n->id,
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function markRead(Request $request, string $notification): JsonResponse
    {
        $this->authorizedUser($request)->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return $this->success(null, 'Notification lue');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->authorizedUser($request)->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(null, 'Toutes les notifications sont lues');
    }

    protected function authorizedUser(Request $request)
    {
        $user = $request->user();
        abort_unless($user->can(PermissionEnum::NOTIFICATIONS_VIEW->value), 403, 'This action is unauthorized.');

        return $user;
    }
}
