<?php

namespace App\Services;

use App\Enums\PermissionEnum;
use App\Filters\CallFilter;
use App\Models\Lead;
use App\Models\User;
use App\Repositories\Contracts\CallRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CallService
{
    public function __construct(protected CallRepositoryInterface $calls) {}

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
