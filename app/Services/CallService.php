<?php

namespace App\Services;

use App\Enums\CallDirectionEnum;
use App\Enums\CallStatusEnum;
use App\Enums\PermissionEnum;
use App\Exceptions\ApiException;
use App\Filters\CallFilter;
use App\Models\Call;
use App\Models\Lead;
use App\Models\User;
use App\Repositories\Contracts\CallRepositoryInterface;
use App\Services\Ringover\RingoverClient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

class CallService
{
    public function __construct(
        protected CallRepositoryInterface $calls,
        protected RingoverClient $ringover,
    ) {}

    public function paginateForUser(User $user, CallFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->calls->paginateFiltered($filters, $perPage, $this->visibilityScope($user));
    }

    /**
     * Calls of one lead, limited to those the user may see: an agent only
     * sees their own calls with the lead, not their colleagues'.
     */
    public function paginateForLead(Lead $lead, User $user, CallFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->calls->paginateFiltered($filters, $perPage, function (Builder $query) use ($lead, $user) {
            $query->where('lead_id', $lead->id);
            ($this->visibilityScope($user))($query);
        });
    }

    /**
     * Record that an agent is calling a lead from the CRM. The call is then
     * dialled by the embedded Ringover phone or, with $viaMobile, by Ringover
     * ringing the agent's own devices first. Ringover's own events complete it.
     */
    public function initiate(Lead $lead, User $agent, bool $viaMobile = false): Call
    {
        if (! $agent->isRingoverLinked() || $agent->ringover_number === null) {
            throw new ApiException('Votre compte n\'est pas lié à Ringover. Contactez votre administrateur.', 422);
        }

        if (! $lead->isCallable()) {
            throw new ApiException('Ce lead ne peut pas être appelé (numéro invalide ou marqué comme erroné).', 422);
        }

        $call = Call::create([
            'lead_id' => $lead->id,
            'user_id' => $agent->id,
            'team_id' => $agent->team_id ?? $lead->team_id,
            'direction' => CallDirectionEnum::OUT,
            'status' => CallStatusEnum::INITIATED,
            'source' => 'crm',
            'from_number' => $agent->ringover_number,
            'to_number' => $lead->phone_e164,
            'contact_number' => $lead->phone_e164,
            'started_at' => now(),
        ]);

        if ($viaMobile) {
            try {
                $this->ringover->requestCallback($agent->ringover_number, $lead->phone_e164);
            } catch (Throwable $e) {
                // Nothing was dialled: do not leave an attempt in the history.
                $call->delete();

                throw $e;
            }
        }

        return $call;
    }

    /**
     * Attach Ringover's call id, reported by the embedded phone, to a call
     * started from the CRM. If Ringover's webhook already created that call,
     * the two are merged so the history keeps a single entry.
     */
    public function linkRingoverCall(Call $call, string $ringoverCallId): Call
    {
        return DB::transaction(function () use ($call, $ringoverCallId) {
            $call = Call::lockForUpdate()->findOrFail($call->id);

            if ($call->ringover_call_id === $ringoverCallId) {
                return $call;
            }

            if ($call->ringover_call_id !== null) {
                throw new ApiException('Cet appel est déjà associé à un autre appel Ringover.', 409);
            }

            $existing = Call::where('ringover_call_id', $ringoverCallId)->lockForUpdate()->first();

            if ($existing === null) {
                $call->update(['ringover_call_id' => $ringoverCallId]);

                return $call;
            }

            $existing->lead_id ??= $call->lead_id;
            $existing->user_id ??= $call->user_id;
            $existing->team_id ??= $call->team_id;
            $existing->note ??= $call->note;
            $existing->source = 'crm';
            $existing->save();
            $call->delete();

            return $existing;
        });
    }

    /**
     * Attach a call to a lead by hand (calls from numbers no lead had yet).
     */
    public function assignLead(Call $call, Lead $lead): Call
    {
        $call->update([
            'lead_id' => $lead->id,
            'team_id' => $call->team_id ?? $lead->team_id,
        ]);

        return $call;
    }

    public function updateNote(Call $call, ?string $note): Call
    {
        $call->update(['note' => $note]);

        return $call;
    }

    /**
     * Same rules as CallPolicy::view, as a query constraint.
     */
    protected function visibilityScope(User $user): \Closure
    {
        return function (Builder $query) use ($user) {
            if ($user->can(PermissionEnum::CALLS_VIEW_ALL->value)) {
                return;
            }

            $canTeam = $user->can(PermissionEnum::CALLS_VIEW_TEAM->value) && $user->team_id !== null;
            $canOwn = $user->can(PermissionEnum::CALLS_VIEW_OWN->value);

            if (! $canTeam && ! $canOwn) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where(function (Builder $query) use ($user, $canTeam, $canOwn) {
                if ($canOwn) {
                    $query->orWhere('user_id', $user->id);
                }

                if ($canTeam) {
                    $query->orWhere('team_id', $user->team_id)
                        ->orWhereHas('lead', fn (Builder $lead) => $lead->where('team_id', $user->team_id));
                }
            });
        };
    }
}
