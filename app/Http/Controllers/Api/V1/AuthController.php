<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;

class AuthController extends Controller
{
    public function __construct(protected AuthService $authService) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login($request->validated(), (string) $request->ip());

        // Password accepted but the machine must prove itself first: no session yet
        if ($result['token'] === null) {
            return $this->success(['device' => $result['device']], 'Machine verification required');
        }

        return $this->success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
            'device' => $result['device'],
            // The next "remember this machine" mark, when the PIN was skipped
            'device_mark' => $result['device_mark'],
        ], 'Login successful');
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->success(null, 'Logged out successfully');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()), 'Authenticated user');
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->authService->updateProfile(
            $request->user(),
            $request->validated()
        );

        return $this->success(
            new UserResource($user),
            'Profile updated successfully'
        );
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->authService->changePassword(
            $request->user(),
            $request->validated()
        );

        return $this->success(
            null,
            'Password updated successfully'
        );
    }
}
