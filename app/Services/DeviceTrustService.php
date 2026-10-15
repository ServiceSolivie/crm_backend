<?php

namespace App\Services;

use App\Enums\DeviceRestrictionEnum;
use App\Enums\TrustedDeviceStatusEnum;
use App\Exceptions\ApiException;
use App\Models\AppSetting;
use App\Models\TrustedDevice;
use App\Models\TrustedDeviceMark;
use App\Models\User;
use App\Notifications\DeviceApprovalRequestedNotification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use lbuchs\WebAuthn\Attestation\AuthenticatorData;
use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\WebAuthnException;
use Throwable;

/**
 * Approved machines: the second step of the login.
 *
 * The password says who is logging in; the machine then proves it is an
 * approved PC by signing a one-time challenge with a passkey that Windows
 * Hello keeps locked on that PC. The CRM only holds the public half.
 *
 * Between the two steps the browser holds a ticket, not a session: nothing
 * of the CRM opens with it.
 */
class DeviceTrustService
{
    public const SETTING = 'device_restriction';

    /** Name of the tokens the vault browser extension logs in with */
    public const VAULT_TOKEN = 'vault-extension';

    /* ── The switch ─────────────────────────────────────────────── */

    public function mode(): DeviceRestrictionEnum
    {
        return DeviceRestrictionEnum::tryFrom((string) AppSetting::read(self::SETTING)) ?? DeviceRestrictionEnum::OFF;
    }

    /**
     * Move the switch. Turning the restriction on closes every session that
     * was not opened through an approved machine.
     *
     * @return int sessions closed
     */
    public function setMode(DeviceRestrictionEnum $mode): int
    {
        AppSetting::write(self::SETTING, $mode->value);

        return $mode === DeviceRestrictionEnum::ON ? $this->closeUnprovenSessions() : 0;
    }

    public function isExempt(User $user): bool
    {
        return $user->hasAnyRole(config('devices.exempt_roles', []));
    }

    /**
     * Must this user be on an approved machine right now?
     */
    public function enforcedFor(User $user): bool
    {
        return $this->mode() === DeviceRestrictionEnum::ON && ! $this->isExempt($user);
    }

    /**
     * Was this session opened through a machine (as opposed to a plain
     * password login)?
     */
    public function openedThroughMachine(?PersonalAccessToken $token): bool
    {
        return $token !== null && str_starts_with((string) $token->name, TrustedDevice::TOKEN_PREFIX);
    }

    /* ── Step 1: password accepted, what is asked of the machine ─── */

    /**
     * Null when nothing is asked of the machine (switch off, or exempt user).
     *
     * @param  string|null  $hint  passkey id the browser remembers for this machine
     * @return array{ticket: string, mode: string, known: bool, options: array<string, mixed>}|null
     */
    public function begin(User $user, ?string $hint = null): ?array
    {
        $mode = $this->mode();

        if ($mode === DeviceRestrictionEnum::OFF || $this->isExempt($user)) {
            return null;
        }

        $device = $hint ? TrustedDevice::where('credential_id', $hint)->first() : null;

        $webAuthn = $this->webAuthn();
        // Without a remembered id the PC offers the passkey it holds for the CRM, if any
        $args = $webAuthn->getGetArgs(
            $device ? [self::fromBase64Url($device->credential_id)] : [],
            (int) config('devices.prompt_timeout'),
            false, false, false, false, true,
            'required',
        );

        $ticket = Str::random(64);
        $this->storeTicket($ticket, [
            'user_id' => $user->id,
            'mode' => $mode->value,
            'challenge' => base64_encode($webAuthn->getChallenge()->getBinaryString()),
        ]);

        return [
            'ticket' => $ticket,
            'mode' => $mode->value,
            'known' => $device !== null,
            'options' => self::toArray($args->publicKey),
        ];
    }

    /* ── Registering an unknown machine ─────────────────────────── */

    /**
     * What the browser passes to Windows to create the passkey.
     *
     * @return array<string, mixed>
     */
    public function registrationOptions(string $ticket): array
    {
        $payload = $this->readTicket($ticket);
        $webAuthn = $this->webAuthn();

        $args = $webAuthn->getCreateArgs(
            random_bytes(16),
            'poste-'.now()->format('Ymd-His'),
            config('devices.rp_name').' · poste',
            (int) config('devices.prompt_timeout'),
            true,   // kept on the PC, findable again if the browser forgets its id
            true,   // PIN or biometrics required
            false,  // the PC itself, not a USB key or a phone
        );

        $options = self::toArray($args->publicKey);
        // The certificate of the chip is not needed, and asking for it shows an extra consent window
        $options['attestation'] = 'none';
        $options['hints'] = ['client-device'];
        unset($options['extensions'], $options['excludeCredentials']);

        $payload['register_challenge'] = base64_encode($webAuthn->getChallenge()->getBinaryString());
        $this->storeTicket($ticket, $payload);

        return $options;
    }

    /**
     * Save the machine as waiting for approval.
     *
     * @param  array{clientDataJSON: string, attestationObject: string}  $credential  base64url, as the browser returned them
     */
    public function register(string $ticket, array $credential, ?string $ip, ?string $userAgent): TrustedDevice
    {
        $payload = $this->readTicket($ticket);

        if (empty($payload['register_challenge'])) {
            throw new ApiException('La demande d\'enregistrement a expiré. Reconnectez-vous.', 403, ['code' => 'ticket_expired']);
        }

        $clientData = self::fromBase64Url($credential['clientDataJSON']);
        $this->assertOrigin($clientData);

        try {
            $data = $this->webAuthn()->processCreate(
                $clientData,
                self::fromBase64Url($credential['attestationObject']),
                base64_decode($payload['register_challenge']),
                true,
                true,
                false,
            );
        } catch (WebAuthnException $e) {
            throw new ApiException('L\'enregistrement de ce poste a échoué. Réessayez.', 422, ['code' => 'device_invalid', 'reason' => $e->getMessage()]);
        }

        // A passkey saved in a Google / Microsoft account follows the person to every device
        if ($data->isBackupEligible || $data->isBackedUp) {
            throw new ApiException(
                'Cette clé est enregistrée dans un compte (Google ou Microsoft) et non sur ce poste. Recommencez en choisissant « Windows Hello » ou « cet appareil ».',
                422,
                ['code' => 'device_synced_key'],
            );
        }

        unset($payload['register_challenge']);
        $this->storeTicket($ticket, $payload);

        $credentialId = self::toBase64Url($data->credentialId);

        if ($existing = TrustedDevice::where('credential_id', $credentialId)->first()) {
            return $existing;
        }

        $waiting = TrustedDevice::where('registered_by', $payload['user_id'])
            ->where('status', TrustedDeviceStatusEnum::PENDING->value)
            ->count();

        if ($waiting >= (int) config('devices.max_pending_per_user')) {
            throw new ApiException('Vous avez déjà plusieurs demandes en attente. Contactez votre administrateur.', 429, ['code' => 'device_too_many_requests']);
        }

        $device = TrustedDevice::create([
            'credential_id' => $credentialId,
            'public_key' => $data->credentialPublicKey,
            'sign_count' => (int) ($data->signatureCounter ?? 0),
            'status' => TrustedDeviceStatusEnum::PENDING->value,
            'registered_by' => $payload['user_id'],
            'last_ip' => $ip,
            'user_agent' => $userAgent ? mb_substr($userAgent, 0, 512) : null,
        ]);

        $this->notifyAdmins($device);

        return $device;
    }

    /* ── Step 2: the machine signs the challenge ────────────────── */

    /**
     * The token is null during the rehearsal: the session was already given
     * at step 1.
     *
     * @param  array{id: string, clientDataJSON: string, authenticatorData: string, signature: string}  $assertion  base64url
     * @return array{user: User, device: TrustedDevice, token: ?string}
     */
    public function verify(string $ticket, array $assertion, ?string $ip): array
    {
        // One try per ticket: a challenge is never accepted twice
        $payload = $this->readTicket($ticket, consume: true);

        $device = TrustedDevice::where('credential_id', $assertion['id'])->first();

        if (! $device) {
            throw new ApiException('Ce poste n\'est pas enregistré.', 403, ['code' => 'device_unknown']);
        }

        $clientData = self::fromBase64Url($assertion['clientDataJSON']);
        $authenticatorData = self::fromBase64Url($assertion['authenticatorData']);
        $this->assertOrigin($clientData);

        $webAuthn = $this->webAuthn();

        try {
            $webAuthn->processGet(
                $clientData,
                $authenticatorData,
                self::fromBase64Url($assertion['signature']),
                $device->public_key,
                base64_decode($payload['challenge']),
                $device->sign_count,
                true,
                true,
            );
        } catch (WebAuthnException $e) {
            // A counter going backwards means the key exists twice: the machine is cut off
            if ($e->getCode() === WebAuthnException::SIGNATURE_COUNTER && $device->isApproved()) {
                $this->revoke($device, 'counter');
            }

            throw new ApiException('La vérification de ce poste a échoué.', 403, ['code' => 'device_invalid', 'reason' => $e->getMessage()]);
        }

        if ((new AuthenticatorData($authenticatorData))->getIsBackup()) {
            throw new ApiException('Cette clé n\'est plus liée à ce seul poste.', 403, ['code' => 'device_synced_key']);
        }

        if ($device->status === TrustedDeviceStatusEnum::PENDING) {
            throw new ApiException('Ce poste attend l\'approbation d\'un administrateur.', 403, ['code' => 'device_pending']);
        }

        if ($device->status === TrustedDeviceStatusEnum::REVOKED) {
            throw new ApiException('Ce poste n\'est plus autorisé. Contactez votre administrateur.', 403, ['code' => 'device_revoked']);
        }

        $user = User::findOrFail($payload['user_id']);

        $device->update([
            'sign_count' => $webAuthn->getSignatureCounter() ?? $device->sign_count,
            'last_used_at' => now(),
            'last_used_by' => $user->id,
            'last_ip' => $ip,
        ]);

        // Rehearsal: the session was given with the password; this only proves the machine works
        $token = $payload['mode'] === DeviceRestrictionEnum::ON->value
            ? $user->createToken($device->tokenName())->plainTextToken
            : null;

        return ['user' => $user, 'device' => $device, 'token' => $token, 'mark' => $this->issueMark($device)];
    }

    /* ── Remembering a machine between two PIN checks ───────────── */

    /**
     * A login that skips the PIN because this browser holds a valid mark of
     * an approved machine. The mark is exchanged for a new one that keeps the
     * same expiry: the PIN is asked again when it runs out.
     *
     * @return array{device: TrustedDevice, mark: string}|null null when the PIN step applies
     */
    public function rememberedLogin(User $user, ?string $mark, ?string $ip): ?array
    {
        if (! $mark || $this->mode() === DeviceRestrictionEnum::OFF || $this->isExempt($user)) {
            return null;
        }

        $row = TrustedDeviceMark::with('device')->where('token_hash', hash('sha256', $mark))->first();

        if (! $row || $row->expires_at->isPast()) {
            return null;
        }

        // A mark that was already exchanged comes back: this browser's storage was
        // copied. Every mark of the machine goes, the real PC proves itself with its PIN.
        if ($row->used_at !== null) {
            TrustedDeviceMark::where('trusted_device_id', $row->trusted_device_id)->delete();
            Log::warning('trusted_device: a remembered-machine mark was used twice, marks cancelled', [
                'device_id' => $row->trusted_device_id,
                'user_id' => $user->id,
                'ip' => $ip,
            ]);

            return null;
        }

        $device = $row->device;

        if (! $device || ! $device->isApproved()) {
            return null;
        }

        // Two logins racing with the same mark: only one gets it
        if (! TrustedDeviceMark::whereKey($row->id)->whereNull('used_at')->update(['used_at' => now()])) {
            return null;
        }

        $device->update(['last_used_at' => now(), 'last_used_by' => $user->id, 'last_ip' => $ip]);

        return ['device' => $device, 'mark' => $this->issueMark($device, $row->expires_at)];
    }

    /**
     * A new mark for this machine. Without an expiry it is a fresh PIN check:
     * remembered for the configured number of days.
     */
    public function issueMark(TrustedDevice $device, ?Carbon $expiresAt = null): ?string
    {
        if (! $device->isApproved()) {
            return null;
        }

        // Expired marks of the machine are no longer needed, even to recognise a copy
        TrustedDeviceMark::where('trusted_device_id', $device->id)->where('expires_at', '<', now())->delete();

        $plain = Str::random(64);
        TrustedDeviceMark::create([
            'trusted_device_id' => $device->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresAt ?? now()->addDays((int) config('devices.remember_days')),
        ]);

        return $plain;
    }

    /* ── Sessions ───────────────────────────────────────────────── */

    public function revoke(TrustedDevice $device, ?string $reason = null): void
    {
        $device->update([
            'status' => TrustedDeviceStatusEnum::REVOKED->value,
            'revoked_at' => now(),
            'revoked_reason' => $reason,
        ]);

        // Its browsers are no longer remembered: if it is reactivated, the PIN comes first
        TrustedDeviceMark::where('trusted_device_id', $device->id)->delete();

        $this->closeSessionsOf($device);
    }

    public function closeSessionsOf(TrustedDevice $device): int
    {
        return PersonalAccessToken::where('name', $device->tokenName())->delete();
    }

    /**
     * Close the sessions of non-exempt users that were not opened through an
     * approved machine. The vault extension's own sessions are left alone.
     */
    public function closeUnprovenSessions(): int
    {
        $exemptIds = User::role(config('devices.exempt_roles', []))->pluck('id');
        $approved = TrustedDevice::where('status', TrustedDeviceStatusEnum::APPROVED->value)
            ->get()
            ->map(fn (TrustedDevice $device) => $device->tokenName());

        return PersonalAccessToken::query()
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereNotIn('tokenable_id', $exemptIds)
            ->where('name', '!=', self::VAULT_TOKEN)
            ->whereNotIn('name', $approved)
            ->delete();
    }

    /* ── Internals ──────────────────────────────────────────────── */

    protected function webAuthn(): WebAuthn
    {
        // Base64url on both sides: what the browser's own helpers produce
        return new WebAuthn(config('devices.rp_name'), config('devices.rp_id'), ['none', 'packed', 'tpm'], true);
    }

    /**
     * The signature must have been produced on the CRM's own address. The
     * library accepts any host ending with the configured one; this is the
     * exact check.
     */
    protected function assertOrigin(string $clientDataJson): void
    {
        $origin = rtrim((string) (json_decode($clientDataJson, true)['origin'] ?? ''), '/');

        if ($origin === '' || ! in_array($origin, config('devices.origins', []), true)) {
            throw new ApiException('Cette demande ne vient pas de l\'adresse du CRM.', 403, ['code' => 'device_invalid', 'reason' => 'origin']);
        }
    }

    protected function notifyAdmins(TrustedDevice $device): void
    {
        try {
            $admins = User::role(config('devices.exempt_roles', []))->where('is_active', true)->get();
            Notification::send($admins, new DeviceApprovalRequestedNotification($device->load('registeredBy')));
        } catch (Throwable $e) {
            // The request is saved and shows on the page: a notification that fails must not undo it
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function storeTicket(string $ticket, array $payload): void
    {
        Cache::put(self::ticketKey($ticket), $payload, (int) config('devices.ticket_ttl'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function readTicket(string $ticket, bool $consume = false): array
    {
        $payload = $consume ? Cache::pull(self::ticketKey($ticket)) : Cache::get(self::ticketKey($ticket));

        if (! is_array($payload) || empty($payload['user_id'])) {
            throw new ApiException('Votre connexion a expiré. Saisissez à nouveau votre mot de passe.', 403, ['code' => 'ticket_expired']);
        }

        return $payload;
    }

    protected static function ticketKey(string $ticket): string
    {
        return 'device_ticket:'.hash('sha256', $ticket);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function toArray(object $options): array
    {
        return json_decode(json_encode($options), true);
    }

    public static function toBase64Url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }

    public static function fromBase64Url(string $text): string
    {
        return (string) base64_decode(strtr($text, '-_', '+/').str_repeat('=', (4 - strlen($text) % 4) % 4));
    }
}
