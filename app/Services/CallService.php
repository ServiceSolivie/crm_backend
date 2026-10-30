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
use App\Support\PhoneNumber;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CallService
{
    public function __construct(
        protected CallRepositoryInterface $calls,
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
     * dialled by the embedded Ringover phone, from whichever Ringover account
     * the agent signs in with. Ringover's own events complete it.
     *
     * Ringover's POST /callback (ring the agent's mobile first, then the lead)
     * is deliberately not used: Ringover bills it as two calls.
     */
    public function initiate(Lead $lead, User $agent): Call
    {
        if (! $lead->isCallable()) {
            throw new ApiException('Ce lead ne peut pas être appelé (aucun numéro, ou marqué comme erroné).', 422);
        }

        return Call::create([
            'lead_id' => $lead->id,
            'user_id' => $agent->id,
            'team_id' => $agent->team_id ?? $lead->team_id,
            'direction' => CallDirectionEnum::OUT,
            'status' => CallStatusEnum::INITIATED,
            'source' => 'crm',
            'from_number' => $agent->ringover_number,
            // Dialled exactly as stored; the normalised copy is what Ringover reports back.
            'to_number' => PhoneNumber::forDialing($lead->phone),
            'contact_number' => $lead->phone_e164 ?? PhoneNumber::forDialing($lead->phone),
            'started_at' => now(),
        ]);
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
