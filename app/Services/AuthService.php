<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(protected UserRepositoryInterface $users) {}

    /** Failed attempts allowed per e-mail + IP before the login is locked */
    public const MAX_LOGIN_ATTEMPTS = 4;

    /** How long the login stays locked after too many failures (seconds) */
    public const LOGIN_LOCK_SECONDS = 60;

    /**
     * Attempt to authenticate a user and issue an API token.
     *
     * Brute-force protection: after MAX_LOGIN_ATTEMPTS wrong passwords for
     * the same e-mail from the same IP, further attempts are refused (429)
     * for LOGIN_LOCK_SECONDS. A successful login resets the counter.
     *
     * @return array{user: User, token: string}
     *
     * @throws ApiException
     */
    public function login(array $credentials, string $ip = ''): array
    {
        $key = 'login:'.Str::lower(trim($credentials['email'])).'|'.$ip;

        if (RateLimiter::tooManyAttempts($key, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw new ApiException(
                "Trop de tentatives de connexion. Réessayez dans {$seconds} secondes.",
                429,
                ['retry_after' => $seconds],
            );
        }

        /** @var User|null $user */
        $user = $this->users->findBy('email', $credentials['email']);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, self::LOGIN_LOCK_SECONDS);

            throw new ApiException('These credentials do not match our records.', 401, [
                'attempts_left' => max(0, self::MAX_LOGIN_ATTEMPTS - RateLimiter::attempts($key)),
            ]);
        }

        RateLimiter::clear($key);

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

    public function updateProfile(User $user, array $data): User
    {
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        return $user->fresh();
    }

    public function changePassword(User $user, array $data): void
    {
        if (! Hash::check(
            $data['current_password'],
            $user->password
        )) {
            throw ValidationException::withMessages([
                'current_password' => [
                    'Current password is incorrect'
                ]
            ]);
        }

        $user->update([
            'password' => Hash::make(
                $data['password']
            ),
        ]);
    }
}
