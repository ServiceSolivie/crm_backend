<?php

namespace App\Services;

use App\Enums\ActivityCategoryEnum;
use App\Enums\ActivityEventEnum;
use App\Models\ActivityLog;
use App\Models\ActivityOperation;
use App\Models\Lead;
use App\Models\PaymentSession;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Writes the activity journal: one log under its operation (a payment, a
 * Google Ads submission, an account's logins of the day…), and how that
 * operation stands after it (ActivityEventEnum::state()).
 *
 * Recording is a side effect and never breaks the feature that calls it:
 * any failure is reported and swallowed. A log asked for inside a database
 * transaction is only written once that transaction commits, so a rolled
 * back operation leaves no false entry.
 *
 * Nothing secret is ever stored (see SECRET_KEYS), nor a full provider
 * payload: pass identifiers, statuses, codes and messages only.
 */
class ActivityLogger
{
    /** Property names dropped before saving, whatever the caller passed */
    protected const SECRET_KEYS = ['google_key', 'api_key', 'api-key', 'password', 'current_password', 'client_secret', 'sdk_authorization', 'token', 'secret'];

    /** "Hyperswitch does not answer" is logged at most once per … seconds */
    public const UNREACHABLE_EVERY = 600;

    /* ── One method per kind of operation ────────────────────────────── */

    /**
     * A log of a payment request (its refund logs live in the same operation).
     *
     * @param  array<string, mixed>  $properties
     */
    public function payment(PaymentSession $session, ActivityEventEnum $event, ?string $message = null, array $properties = [], User|string|null $actor = null): void
    {
        $this->record($event, fn () => self::sessionOperation($session), $message, $properties, $actor);
    }

    /**
     * A log of a Google Ads lead form submission. Requests that are not
     * authenticated (wrong key, unreadable body) can't be trusted to name
     * their own operation: they are grouped per day.
     *
     * @param  array<string, mixed>  $properties
     */
    public function googleAds(?string $googleLeadId, ActivityEventEnum $event, ?string $message = null, array $properties = [], ?string $subtitle = null, ?int $leadId = null): void
    {
        $day = now();

        $this->record($event, fn () => $googleLeadId !== null && $googleLeadId !== ''
            ? [
                'key' => 'gads:'.mb_substr($googleLeadId, 0, 150),
                'category' => ActivityCategoryEnum::GOOGLE_ADS,
                'title' => 'Lead Google Ads '.mb_substr($googleLeadId, 0, 60),
                'subtitle' => $subtitle,
                'reference' => mb_substr($googleLeadId, 0, 191),
                'lead_id' => $leadId,
            ]
            : [
                'key' => 'gads:rejected:'.$day->toDateString(),
                'category' => ActivityCategoryEnum::GOOGLE_ADS,
                'title' => 'Requêtes Google Ads non authentifiées',
                'subtitle' => $day->format('d/m/Y'),
            ], $message, $properties, ActivityLog::ACTOR_GOOGLE_ADS);
    }

    /**
     * A log of an account's logins: one operation per e-mail and per day.
     * $authenticated false: an attempt, nobody is known to have done it.
     *
     * @param  array<string, mixed>  $properties
     */
    public function auth(string $email, ActivityEventEnum $event, ?string $message = null, array $properties = [], ?User $user = null, bool $authenticated = true): void
    {
        $email = mb_strtolower(trim($email));
        $day = now();

        $this->record($event, fn () => [
            'key' => 'auth:'.mb_substr($email, 0, 150).':'.$day->toDateString(),
            'category' => ActivityCategoryEnum::AUTH,
            'title' => 'Connexions de '.($user?->name ?: $email),
            'subtitle' => $day->format('d/m/Y').($user ? ' · '.$email : ''),
            'reference' => $email,
            'subject' => $user,
        ], $message, $properties, $authenticated && $user ? $user : ActivityLog::ACTOR_USER);
    }

    /**
     * A login attempt on an e-mail that is no account. Anyone can type any
     * address: these are grouped in one operation per day (the address
     * tried is in the details), not one operation per address.
     *
     * @param  array<string, mixed>  $properties
     */
    public function unknownAccount(string $email, ActivityEventEnum $event, ?string $message = null, array $properties = []): void
    {
        $day = now();

        $this->record($event, fn () => [
            'key' => 'auth:unknown:'.$day->toDateString(),
            'category' => ActivityCategoryEnum::AUTH,
            'title' => 'Tentatives sur des comptes inconnus',
            'subtitle' => $day->format('d/m/Y'),
        ], $message, ['email' => mb_substr(mb_strtolower(trim($email)), 0, 191), ...$properties], ActivityLog::ACTOR_USER);
    }

    /**
     * A log of what was done to a user account: one operation per user and per day.
     *
     * @param  array<string, mixed>  $properties
     */
    public function user(User $target, ActivityEventEnum $event, ?string $message = null, array $properties = [], User|string|null $actor = null): void
    {
        $day = now();

        $this->record($event, fn () => [
            'key' => 'user:'.$target->id.':'.$day->toDateString(),
            'category' => ActivityCategoryEnum::USER,
            'title' => 'Utilisateur '.$target->name,
            'subtitle' => $target->email.' · '.$day->format('d/m/Y'),
            'reference' => $target->email,
            'subject' => $target,
        ], $message, $properties, $actor);
    }

    /**
     * A change of a lead's contract total: one operation per lead and per day.
     *
     * @param  array<string, mixed>  $properties
     */
    public function contractTotal(Lead $lead, ActivityEventEnum $event, ?string $message = null, array $properties = [], User|string|null $actor = null): void
    {
        $day = now();

        $this->record($event, fn () => [
            'key' => 'lead-total:'.$lead->id.':'.$day->toDateString(),
            'category' => ActivityCategoryEnum::PAYMENT,
            'title' => 'Total du contrat',
            'subtitle' => self::leadName($lead).' · '.$lead->reference,
            'reference' => $lead->reference,
            'lead_id' => $lead->id,
            'subject' => $lead,
        ], $message, $properties, $actor);
    }

    /**
     * Hyperswitch gave no answer. During an outage every check fails: this
     * is logged at most once per UNREACHABLE_EVERY seconds, in one operation
     * per day.
     */
    public function hyperswitchUnreachable(string $during): void
    {
        if (! $this->allowed('hyperswitch-unreachable', self::UNREACHABLE_EVERY)) {
            return;
        }

        $day = now();

        $this->record(ActivityEventEnum::SYSTEM_HYPERSWITCH_UNREACHABLE, fn () => [
            'key' => 'system:hyperswitch:'.$day->toDateString(),
            'category' => ActivityCategoryEnum::SYSTEM,
            'title' => 'Incidents Hyperswitch',
            'subtitle' => $day->format('d/m/Y'),
        ], "Aucune réponse pendant : {$during}. Les vérifications sont reportées.", ['during' => $during], ActivityLog::ACTOR_SYSTEM);
    }

    /* ── Noise control ───────────────────────────────────────────────── */

    /**
     * True at most once per $seconds for this key (e.g. failed logins of
     * one e-mail from one IP): the caller only logs when it is true.
     */
    public function allowed(string $key, int $seconds): bool
    {
        try {
            return Cache::add('activity-log:'.sha1($key), true, $seconds);
        } catch (Throwable $e) {
            report($e);

            return true;
        }
    }

    /* ── Writing ─────────────────────────────────────────────────────── */

    /**
     * Record one event under its operation.
     *
     * @param  callable(): array{key: string, category: ActivityCategoryEnum, title: string, subtitle?: ?string, reference?: ?string, lead_id?: ?int, subject?: ?Model}  $operation  built lazily, inside the guard
     * @param  array<string, mixed>  $properties
     */
    public function record(ActivityEventEnum $event, callable $operation, ?string $message = null, array $properties = [], User|string|null $actor = null): void
    {
        try {
            $descriptor = $operation();
            [$actorType, $actorId] = $this->resolveActor($actor);
            [$ip, $userAgent] = self::originOf($actorType, app()->runningInConsole() ? null : request());
            $at = now();
            $properties = self::withoutSecrets($properties);

            // Inside a transaction: written only if it commits. Outside: at once.
            DB::afterCommit(function () use ($event, $descriptor, $message, $properties, $actorType, $actorId, $ip, $userAgent, $at) {
                try {
                    $this->write($event, $descriptor, $message, $properties, $actorType, $actorId, $ip, $userAgent, $at);
                } catch (Throwable $e) {
                    report($e);
                }
            });
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  array<string, mixed>  $descriptor
     * @param  array<string, mixed>  $properties
     */
    protected function write(ActivityEventEnum $event, array $descriptor, ?string $message, array $properties, string $actorType, ?int $actorId, ?string $ip, ?string $userAgent, CarbonInterface $at): void
    {
        DB::transaction(function () use ($event, $descriptor, $message, $properties, $actorType, $actorId, $ip, $userAgent, $at) {
            $operation = $this->lockOperation($descriptor, $at);

            $log = new ActivityLog([
                'activity_operation_id' => $operation->id,
                'event' => $event,
                'category' => $event->category(),
                'level' => $event->level(),
                'message' => $message !== null ? mb_substr($message, 0, 500) : null,
                'actor_type' => $actorType,
                'actor_id' => $actorId,
                'properties' => $properties ?: null,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
            ]);
            $log->created_at = $at;
            $log->save();

            $changes = [
                'logs_count' => $operation->logs_count + 1,
                'problems_count' => $operation->problems_count + ($event->level()->isProblem() ? 1 : 0),
                'last_activity_at' => $at,
            ];
            if ($event->category() === ActivityCategoryEnum::REFUND) {
                $changes['has_refund'] = true;
            }
            if ($state = $event->state()) {
                $changes['state'] = $state;
                $changes['state_label'] = $event->stateLabel();
                $changes['state_event'] = $event->value;
            }
            // What wasn't known when the operation started (e.g. the lead of a Google Ads submission)
            foreach (['lead_id', 'subtitle', 'reference'] as $field) {
                if ($operation->{$field} === null && ($descriptor[$field] ?? null) !== null) {
                    $changes[$field] = $descriptor[$field];
                }
            }

            $operation->update($changes);
        });
    }

    /**
     * The operation of this key, locked: created when it is its first log.
     * Two first logs at the same moment: the unique key keeps one row, the
     * other one takes it on the second try.
     *
     * @param  array<string, mixed>  $descriptor
     */
    protected function lockOperation(array $descriptor, CarbonInterface $at): ActivityOperation
    {
        $find = fn () => ActivityOperation::query()->where('key', $descriptor['key'])->lockForUpdate()->first();

        if ($operation = $find()) {
            return $operation;
        }

        $subject = $descriptor['subject'] ?? null;

        try {
            return ActivityOperation::create([
                'key' => $descriptor['key'],
                'category' => $descriptor['category'],
                'title' => mb_substr((string) $descriptor['title'], 0, 255),
                'subtitle' => isset($descriptor['subtitle']) ? mb_substr((string) $descriptor['subtitle'], 0, 255) : null,
                'reference' => isset($descriptor['reference']) ? mb_substr((string) $descriptor['reference'], 0, 191) : null,
                'lead_id' => $descriptor['lead_id'] ?? null,
                'subject_type' => $subject instanceof Model ? $subject->getMorphClass() : null,
                'subject_id' => $subject instanceof Model ? $subject->getKey() : null,
                'started_at' => $at,
                'last_activity_at' => $at,
            ]);
        } catch (QueryException $e) {
            if ($operation = $find()) {
                return $operation;
            }

            throw $e;
        }
    }

    /**
     * The address and browser a log is attributed to: those of the request,
     * but only when the log is something that request's caller did (a user,
     * the client, Google Ads). A step the system does on its own (e-mailing
     * a link, noticing a payment) has none, even when it happens to run
     * inside someone's request: an address in the journal always means
     * "whoever did this".
     *
     * @return array{0: ?string, 1: ?string}
     */
    public static function originOf(string $actorType, ?Request $request): array
    {
        if ($request === null || $actorType === ActivityLog::ACTOR_SYSTEM) {
            return [null, null];
        }

        return [$request->ip(), mb_substr((string) $request->userAgent(), 0, 512) ?: null];
    }

    /**
     * @return array{0: string, 1: ?int}
     */
    protected function resolveActor(User|string|null $actor): array
    {
        if ($actor instanceof User) {
            return [ActivityLog::ACTOR_USER, $actor->id];
        }

        if (is_string($actor)) {
            return [$actor, null];
        }

        $user = auth()->user();

        return $user instanceof User ? [ActivityLog::ACTOR_USER, $user->id] : [ActivityLog::ACTOR_SYSTEM, null];
    }

    /* ── Helpers ─────────────────────────────────────────────────────── */

    /**
     * The operation of a payment request.
     *
     * @return array<string, mixed>
     */
    public static function sessionOperation(PaymentSession $session): array
    {
        $lead = $session->lead;

        return [
            'key' => 'session:'.$session->id,
            'category' => ActivityCategoryEnum::PAYMENT,
            'title' => 'Paiement '.$session->reference,
            'subtitle' => trim(($lead ? self::leadName($lead).' · ' : '').self::euros($session->amount)),
            'reference' => $session->reference,
            'lead_id' => $session->lead_id,
            'subject' => $session,
        ];
    }

    public static function leadName(Lead $lead): string
    {
        return trim(($lead->first_name ?? '').' '.($lead->last_name ?? '')) ?: (string) $lead->reference;
    }

    public static function euros(string|float|int|null $amount): string
    {
        return number_format((float) $amount, 2, ',', ' ').' €';
    }

    /**
     * Drop anything that looks like a secret, at any depth.
     *
     * @param  array<string|int, mixed>  $properties
     * @return array<string|int, mixed>
     */
    public static function withoutSecrets(array $properties): array
    {
        $clean = [];

        foreach ($properties as $key => $value) {
            if (is_string($key) && in_array(mb_strtolower($key), self::SECRET_KEYS, true)) {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            $clean[$key] = is_array($value) ? self::withoutSecrets($value) : $value;
        }

        return $clean;
    }
}
