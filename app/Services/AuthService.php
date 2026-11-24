<?php

namespace App\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(protected UserRepositoryInterface $users, protected DeviceTrustService $devices) {}

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
     * When the approved-machines restriction is on, the password alone gives
     * no token: `device` then carries the ticket and the challenge the
     * machine must sign (see DeviceTrustService). During the rehearsal the
     * token is given as before and `device` only offers that second step.
     *
     * A browser of an approved machine whose PIN was checked less than
     * `devices.remember_days` ago skips that second step (`device_mark`).
     *
     * @return array{user: User, token: ?string, device: ?array<string, mixed>, device_mark: ?string}
     *
     * @throws ApiException
     */
    public function login(array $credentials, string $ip = '', string $userAgent = ''): array
    {
        $key = 'login:'.Str::lower(trim($credentials['email'])).'|'.$ip;

        /** @var User|null $user */
        $user = $this->users->findBy('email', $credentials['email']);

        // Who tried to log in, for the error log / Slack alert (never the password)
        $who = fn (string $reason, array $more = []) => [
            'raison' => $reason,
            'email_saisi' => $credentials['email'],
            'compte' => $user ? "{$user->name} (#{$user->id})".($user->is_active ? '' : ' — désactivé') : 'aucun compte avec cet e-mail',
            'ip' => $ip ?: '—',
            'navigateur' => $userAgent ?: '—',
        ] + $more;

        if (RateLimiter::tooManyAttempts($key, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            $this->logAttempt('auth.login_blocked', $user, $credentials['email'], ['raison' => 'trop de tentatives', 'bloqué pour' => "{$seconds} s"]);

            throw (new ApiException(
                "Trop de tentatives de connexion. Réessayez dans {$seconds} secondes.",
                429,
                ['retry_after' => $seconds],
            ))->withLogContext($who('connexion bloquée (trop de tentatives)', ['bloque_pour' => "{$seconds} s"]));
        }

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key, self::LOGIN_LOCK_SECONDS);
            $attemptsLeft = max(0, self::MAX_LOGIN_ATTEMPTS - RateLimiter::attempts($key));
            $this->logAttempt('auth.login_failed', $user, $credentials['email'], [
                'raison' => $user ? 'mot de passe incorrect' : 'e-mail inconnu',
                'essais restants' => $attemptsLeft,
            ]);

            throw (new ApiException('These credentials do not match our records.', 401, [
                'attempts_left' => $attemptsLeft,
            ]))->withLogContext($who(
                $user ? 'mot de passe incorrect' : 'e-mail inconnu',
                ['essais_restants' => $attemptsLeft],
            ));
        }

        RateLimiter::clear($key);

        if (! $user->is_active) {
            $this->logAttempt('auth.login_failed', $user, $credentials['email'], ['raison' => 'compte désactivé']);

            throw (new ApiException('This account has been deactivated.', 403))
                ->withLogContext($who('compte désactivé'));
        }

        // A machine whose PIN was checked recently is remembered: no PIN this time
        $remembered = $this->devices->rememberedLogin($user, $credentials['device_mark'] ?? null, $ip);

        if ($remembered !== null) {
            ActivityLogger::log('auth', 'auth.login', $user, 'Connexion (machine reconnue)', user: $user);
            $name = $this->devices->enforcedFor($user) ? $remembered['device']->tokenName() : ($credentials['device_name'] ?? 'api');

            return ['user' => $user, 'token' => $user->createToken($name)->plainTextToken, 'device' => null, 'device_mark' => $remembered['mark']];
        }

        $device = $this->devices->begin($user, $credentials['device_credential_id'] ?? null);

        if ($device !== null && $this->devices->enforcedFor($user)) {
            ActivityLogger::log('auth', 'auth.login_device_check', $user, 'Mot de passe correct, vérification de la machine demandée', user: $user);

            return ['user' => $user, 'token' => null, 'device' => $device, 'device_mark' => null];
        }

        $token = $user->createToken($credentials['device_name'] ?? 'api')->plainTextToken;
        ActivityLogger::log('auth', 'auth.login', $user, 'Connexion', user: $user);

        return ['user' => $user, 'token' => $token, 'device' => $device, 'device_mark' => null];
    }

    /**
     * Revoke the access token used for the current request.
     */
    public function logout(User $user): void
    {
        ActivityLogger::log('auth', 'auth.logout', $user, 'Déconnexion', user: $user);
        $user->currentAccessToken()?->delete();
    }

    /**
     * A refused or blocked login: no one is signed in, the line shows the
     * e-mail typed (never the password) and the account it matches, if any.
     *
     * @param  array<string, mixed>  $meta
     */
    protected function logAttempt(string $action, ?User $user, string $email, array $meta): void
    {
        ActivityLogger::as('—', fn () => ActivityLogger::log(
            'auth',
            $action,
            $user,
            'Tentative de connexion : '.$email,
            meta: ['e-mail saisi' => $email] + $meta,
        ));
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
                    'Current password is incorrect',
                ],
            ]);
        }

        $user->update([
            'password' => Hash::make(
                $data['password']
            ),
        ]);

        ActivityLogger::log('auth', 'auth.password_changed', $user, 'Mot de passe changé par l’utilisateur', user: $user);
    }
}
