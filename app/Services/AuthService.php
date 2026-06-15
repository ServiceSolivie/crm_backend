<?php

namespace App\Services;

use App\Enums\RoleEnum;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function __construct(protected UserRepositoryInterface $users) {}

    /**
     * Register a new user and issue an API token.
     *
     * New self-registered accounts are assigned the Agent role by default;
     * role/team assignment is managed by Super Admins via the Users module.
     *
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        /** @var User $user */
        $user = $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);

        $user->assignRole(RoleEnum::AGENT->value);

        $token = $user->createToken($data['device_name'] ?? 'api')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    /**
     * Attempt to authenticate a user and issue an API token.
     *
     * @return array{user: User, token: string}
     *
     * @throws ApiException
     */
    public function login(array $credentials): array
    {
        /** @var User|null $user */
        $user = $this->users->findBy('email', $credentials['email']);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw new ApiException('These credentials do not match our records.', 401);
        }

        if (! $user->is_active) {
            throw new ApiException('This account has been deactivated.', 403);
        }

        $token = $user->createToken($credentials['device_name'] ?? 'api')->plainTextToken;

        return ['user' => $user, 'token' => $token];
    }

    /**
     * Revoke the access token used for the current request.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
