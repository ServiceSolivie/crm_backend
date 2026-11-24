<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Services\DeviceTrustService;
use App\Support\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Second step of the login when the approved-machines restriction applies.
 * These routes are reached with the ticket given after the password, not
 * with a session.
 */
class DeviceLoginController extends Controller
{
    public function __construct(protected DeviceTrustService $devices) {}

    /**
     * The machine signs the challenge: POST /auth/login/device/verify
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ticket' => ['required', 'string', 'size:64'],
            'id' => ['required', 'string', 'max:512'],
            'clientDataJSON' => ['required', 'string'],
            'authenticatorData' => ['required', 'string'],
            'signature' => ['required', 'string'],
        ]);

        try {
            $result = $this->devices->verify($data['ticket'], $data, $request->ip());
        } catch (\Throwable $e) {
            ActivityLogger::as('—', fn () => ActivityLogger::log('auth', 'auth.device_refused', null, 'Machine refusée à la connexion', meta: ['raison' => $e->getMessage()]));

            throw $e;
        }

        if ($result['token'] !== null) {
            ActivityLogger::log('auth', 'auth.login', $result['user'], 'Connexion (machine vérifiée)', user: $result['user']);
        }

        // The PIN was checked: the browser is remembered for the next logins
        if ($result['token'] === null) {
            return $this->success(['verified' => true, 'device_mark' => $result['mark']], 'Machine verified');
        }

        return $this->success([
            'user' => new UserResource($result['user']->load('leadSources')),
            'token' => $result['token'],
            'device_mark' => $result['mark'],
        ], 'Login successful');
    }

    /**
     * What Windows needs to create the passkey: POST /auth/login/device/register-options
     */
    public function registerOptions(Request $request): JsonResponse
    {
        $data = $request->validate(['ticket' => ['required', 'string', 'size:64']]);

        return $this->success(['options' => $this->devices->registrationOptions($data['ticket'])]);
    }

    /**
     * Save this machine as waiting for approval: POST /auth/login/device/register
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ticket' => ['required', 'string', 'size:64'],
            'clientDataJSON' => ['required', 'string'],
            'attestationObject' => ['required', 'string'],
        ]);

        $device = $this->devices->register($data['ticket'], $data, $request->ip(), $request->userAgent());

        return $this->created([
            'credential_id' => $device->credential_id,
            'status' => $device->status->value,
        ], 'Machine registered, waiting for approval');
    }
}
