<?php

namespace App\Services;

use App\Enums\LeadStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\PermissionEnum;
use App\Filters\LeadFilter;
use App\Models\Lead;
use App\Models\LeadNote;
use App\Models\User;
use App\Repositories\Contracts\LeadRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeadService extends BaseService
{
    public function __construct(protected LeadRepositoryInterface $leads)
    {
        parent::__construct($leads);
    }

    /**
     * Paginate leads, scoped to what the given user is allowed to see.
     */
    public function paginateForUser(User $user, LeadFilter $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->leads->paginateFiltered($filters, $perPage, $this->visibilityScope($user));
    }

    /**
     * Restrict the lead query according to the user's view permissions.
     */
    protected function visibilityScope(User $user): \Closure
    {
        return function (Builder $query) use ($user) {
            if ($user->can(PermissionEnum::LEADS_VIEW_ALL->value)) {
                return;
            }

            if ($user->can(PermissionEnum::LEADS_VIEW_TEAM->value)) {
                $query->where('team_id', $user->team_id);

                return;
            }

            if ($user->can(PermissionEnum::LEADS_VIEW_ASSIGNED->value)) {
                $query->where('assigned_to', $user->id);

                return;
            }

            $query->whereRaw('1 = 0');
        };
    }

    /**
     * Create a new lead, generating a unique reference and recording the
     * initial status history entry.
     */
    public function createLead(array $data, User $creator): Lead
    {
        $data['reference'] = $this->generateReference();
        $data['created_by'] = $creator->id;
        $data['status'] = $data['status'] ?? LeadStatusEnum::NRP->value;

        if (! empty($data['assigned_to']) && empty($data['team_id'])) {
            $data['team_id'] = User::find($data['assigned_to'])?->team_id;
        }

        /** @var Lead $lead */
        $lead = $this->leads->create($data);

        $lead->statusHistories()->create([
            'from_status' => null,
            'to_status' => $lead->status,
            'changed_by' => $creator->id,
            'comment' => 'Lead created',
        ]);

        return $lead->refresh();
    }

    /**
     * Update lead details. Status changes go through updateStatus().
     */
    public function updateLead(Lead $lead, array $data): Lead
    {
        unset($data['status'], $data['reference'], $data['created_by']);

        $lead->update($data);

        return $lead->refresh();
    }

    public function deleteLead(Lead $lead): bool
    {
        return $lead->delete();
    }

    /**
     * Reassign a lead to another user, recording the change in the
     * assignment history.
     */
    public function assign(Lead $lead, int $toUserId, User $assignedBy): Lead
    {
        $fromUserId = $lead->assigned_to;
        $toUser = User::findOrFail($toUserId);

        $lead->update([
            'assigned_to' => $toUserId,
            'team_id' => $toUser->team_id,
        ]);

        $lead->assignmentHistories()->create([
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'assigned_by' => $assignedBy->id,
        ]);

        return $lead->refresh();
    }

    /**
     * Move a lead to a new status, recording the transition in the
     * status history.
     */
    public function updateStatus(Lead $lead, LeadStatusEnum $status, User $changedBy, ?string $comment = null, ?string $expectedRevenue = null): Lead
    {
        $fromStatus = $lead->status;

        if ($fromStatus === LeadStatusEnum::VALIDE && $status !== LeadStatusEnum::VALIDE) {
            if ($lead->payments()->exists()) {
                throw ValidationException::withMessages([
                    'status' => 'Impossible de changer le statut : des paiements sont enregistrés. Supprimez tous les paiements d\'abord.',
                ]);
            }

            $lead->update([
                'status' => $status->value,
                'expected_revenue' => null,
                'payment_status' => null,
                'validated_at' => null,
            ]);
        } elseif ($status === LeadStatusEnum::VALIDE && $fromStatus !== LeadStatusEnum::VALIDE) {
            $lead->update([
                'status' => $status->value,
                'expected_revenue' => $expectedRevenue,
                'payment_status' => PaymentStatusEnum::NON_PAYE->value,
                'validated_at' => now(),
            ]);
        } else {
            $lead->update(['status' => $status->value]);
        }

        $lead->statusHistories()->create([
            'from_status' => $fromStatus,
            'to_status' => $status->value,
            'changed_by' => $changedBy->id,
            'comment' => $comment,
        ]);

        return $lead->refresh()->load(['assignedAgent', 'team', 'leadSource', 'creator']);
    }

    public function addNote(Lead $lead, User $user, string $note): LeadNote
    {
        return $lead->notes()->create([
            'user_id' => $user->id,
            'note' => $note,
        ]);
    }

    public function notes(Lead $lead, int $perPage = 15): LengthAwarePaginator
    {
        return $lead->notes()->with('user')->paginate($perPage);
    }

    public function statusHistory(Lead $lead, int $perPage = 15): LengthAwarePaginator
    {
        return $lead->statusHistories()->with('changedBy')->paginate($perPage);
    }

    public function assignmentHistory(Lead $lead, int $perPage = 15): LengthAwarePaginator
    {
        return $lead->assignmentHistories()->with(['fromUser', 'toUser', 'assignedBy'])->paginate($perPage);
    }

    /**
     * Generate a unique, human-readable lead reference (e.g. LD-20260615-AB12CD).
     */
    protected function generateReference(): string
    {
        do {
            $reference = 'LD-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while ($this->leads->referenceExists($reference));

        return $reference;
    }
}
