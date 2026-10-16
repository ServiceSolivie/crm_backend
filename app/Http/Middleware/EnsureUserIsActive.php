<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Services\DeviceTrustService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Reject requests from authenticated users whose account has been deactivated.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            return ApiResponse::error('This account has been deactivated.', 403);
        }

        $token = $user?->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            // The vault extension logs in with a password only: its token opens the vault, never the CRM
            if ($token->name === DeviceTrustService::VAULT_TOKEN) {
                return ApiResponse::error('This session is limited to the vault extension.', 403);
            }

            // Approved machines: a session that did not go through one is closed
            $devices = app(DeviceTrustService::class);
            if ($devices->enforcedFor($user) && ! $devices->openedThroughMachine($token)) {
                $token->delete();

                return ApiResponse::error('Session fermée : connectez-vous depuis un poste approuvé.', 401);
            }
        }

        return $next($request);
    }
}
