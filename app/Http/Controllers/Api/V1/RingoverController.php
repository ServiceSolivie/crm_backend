<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\RingoverException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ringover\LinkRingoverUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Ringover\RingoverClient;
use App\Services\Ringover\RingoverUserLinker;
use Illuminate\Http\JsonResponse;

/**
 * Admin endpoints for the Ringover integration: connection status and
 * linking CRM users to their Ringover account.
 */
class RingoverController extends Controller
{
    public function __construct(
        protected RingoverClient $client,
        protected RingoverUserLinker $linker,
    ) {}

    public function status(): JsonResponse
    {
        $this->authorize('manage-ringover');

        $status = [
            'configured' => $this->client->isConfigured(),
            'connected' => false,
            'ringover_users_count' => null,
            'linked_users_count' => User::whereNotNull('ringover_user_id')->count(),
            'webhook_secret_configured' => filled(config('services.ringover.webhook_secret')),
            'error' => null,
        ];

        if ($status['configured']) {
            try {
                $status['ringover_users_count'] = $this->client->testConnection();
                $status['connected'] = true;
            } catch (RingoverException $e) {
                $status['error'] = $e->getMessage();
            }
        }

        return $this->success($status);
    }

    public function users(): JsonResponse
    {
        $this->authorize('manage-ringover');

        return $this->success($this->linker->overview());
    }

    public function autoLink(): JsonResponse
    {
        $this->authorize('manage-ringover');

        $result = $this->linker->autoLink();

        return $this->success($result, count($result['linked']).' utilisateur(s) lié(s) automatiquement');
    }

    public function link(LinkRingoverUserRequest $request, User $user): JsonResponse
    {
        $this->authorize('manage-ringover');

        $user = $this->linker->link(
            $user,
            $request->validated('ringover_user_id'),
            $request->validated('ringover_number'),
        );

        return $this->success(new UserResource($user->load('team')), 'Utilisateur lié à Ringover');
    }

    public function unlink(User $user): JsonResponse
    {
        $this->authorize('manage-ringover');

        $user = $this->linker->unlink($user);

        return $this->success(new UserResource($user->load('team')), 'Liaison Ringover supprimée');
    }
}
