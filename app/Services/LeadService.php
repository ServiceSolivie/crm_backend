<?php

namespace App\Services;

use App\Enums\ClientTypeEnum;
use App\Enums\DvcStatusEnum;
use App\Enums\InsuranceTypeEnum;
use App\Enums\LeadStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Filters\LeadFilter;
use App\Models\Lead;
use App\Models\LeadCall;
use App\Models\LeadNote;
use App\Models\User;
use App\Notifications\LeadReceivedNotification;
use App\Repositories\Contracts\LeadRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeadService extends BaseService
{
    /**
     * How recently another lead for the same contact must have been
     * assigned for a new assignment to be considered a doublon.
     */
    public const DOUBLON_WINDOW_DAYS = 3;

    public function __construct(protected LeadRepositoryInterface $leads)
    {
        parent::__construct($leads);
    }

    /**
     * Find another lead sharing this contact's phone/email that was
     * assigned to someone within the doublon window. Used to decide
     * whether a lead is safe to assign yet, both at webhook ingestion
     * and at manual CRM dispatch.
     */
    public function findRecentDoublon(Lead $lead, ?int $excludeUserId = null): ?Lead
    {
        if (! $lead->phone && ! $lead->email) {
            return null;
        }

        $others = Lead::where('id', '!=', $lead->id)
            ->where(function (Builder $q) use ($lead) {
                if ($lead->phone) {
                    $q->orWhere('phone', $lead->phone);
                }
                if ($lead->email) {
                    $q->orWhere('email', $lead->email);
                }
            })
            ->whereNotNull('assigned_to')
            ->when($excludeUserId, fn (Builder $q) => $q->where('assigned_to', '!=', $excludeUserId))
            ->get();

        foreach ($others as $other) {
            // Lead::assignmentHistories() is already ->latest(), so first() is the most recent entry.
            $lastAssignment = $other->assignmentHistories()->first();
            if ($lastAssignment?->created_at?->greaterThan(now()->subDays(self::DOUBLON_WINDOW_DAYS))) {
                return $other;
            }
        }

        return null;
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
    public function visibilityScope(User $user): \Closure
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

            if ($user->can(PermissionEnum::LEADS_VIEW_GESTION_ASSIGNED->value)) {
                $query->where('gestion_assigned_to', $user->id);

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
        $data['status'] = $data['status'] ?? LeadStatusEnum::NOUVEAU->value;

        if (empty($data['client_type']) && ! empty($data['insurance_type'])) {
            $insuranceType = InsuranceTypeEnum::tryFrom($data['insurance_type']);
            if ($insuranceType === InsuranceTypeEnum::AUTO || $insuranceType === InsuranceTypeEnum::MOTO) {
                $data['client_type'] = ClientTypeEnum::INDIVIDUAL->value;
            }
        }

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
     * Create a second lead for a different insurance product, for a
     * contact the agent already has. Carries over the contact's own
     * details so nothing needs retyping, auto-assigns it to the same
     * agent (no separate LEADS_ASSIGN check - they already own the
     * contact), and skips doublon detection entirely since this is a
     * deliberate second product for a known contact, not a duplicate
     * submission of the same one.
     */
    public function createCrossSell(Lead $sourceLead, string $insuranceType, User $agent, ?string $clientType = null, ?string $comment = null): Lead
    {
        $lead = $this->createLead([
            'first_name' => $sourceLead->first_name,
            'last_name' => $sourceLead->last_name,
            'phone' => $sourceLead->phone,
            'email' => $sourceLead->email,
            'city' => $sourceLead->city,
            'address' => $sourceLead->address,
            'birth_date' => $sourceLead->birth_date,
            'lead_source_id' => $sourceLead->lead_source_id,
            'insurance_type' => $insuranceType,
            'client_type' => $clientType,
            'assigned_to' => $agent->id,
            'team_id' => $agent->team_id,
        ], $agent);

        $note = "Vente croisée depuis le lead {$sourceLead->reference} (#{$sourceLead->id})";
        if ($comment) {
            $note .= " : {$comment}";
        }
        $this->addNote($lead, $agent, $note);

        return $lead->load(['assignedAgent', 'team', 'leadSource', 'creator']);
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
    public function assign(Lead $lead, int $toUserId, ?User $assignedBy = null): Lead
    {
        $fromUserId = $lead->assigned_to;
        $toUser = User::findOrFail($toUserId);

        if ($assignedBy && $assignedBy->hasRole(RoleEnum::TEAM_LEADER->value) && $toUser->team_id !== $assignedBy->team_id) {
            throw ValidationException::withMessages([
                'assigned_to' => 'You can only assign leads to agents within your team.',
            ]);
        }

        if ($assignedBy && $doublon = $this->findRecentDoublon($lead, $toUserId)) {
            throw ValidationException::withMessages([
                'assigned_to' => "Ce contact a déjà été assigné à {$doublon->assignedAgent->name} il y a moins de ".self::DOUBLON_WINDOW_DAYS.' jours.',
            ]);
        }

        $lead->update([
            'assigned_to' => $toUserId,
            'team_id' => $toUser->team_id,
        ]);

        $lead->assignmentHistories()->create([
            'from_user_id' => $fromUserId,
            'to_user_id' => $toUserId,
            'assigned_by' => $assignedBy?->id,
        ]);

        $toUser->notify(new LeadReceivedNotification($lead));

        return $lead->refresh()->load(['assignedAgent', 'team', 'leadSource']);
    }

    /**
     * Move a lead to a new status, recording the transition in the
     * status history.
     */
    public function updateStatus(Lead $lead, LeadStatusEnum $status, User $changedBy, ?string $comment = null, ?string $expectedRevenue = null, ?array $meta = null): Lead
    {
        $fromStatus = $lead->status;

        // Gestion and Validé need the client's signed DVC in the dossier
        if (in_array($status, [LeadStatusEnum::GESTION, LeadStatusEnum::VALIDE], true)
            && $status !== $fromStatus
            && $lead->dvc_status !== DvcStatusEnum::SIGNE) {
            throw ValidationException::withMessages([
                'status' => $status === LeadStatusEnum::GESTION
                    ? 'Ajoutez le DVC signé au dossier avant d\'envoyer le lead en gestion.'
                    : 'Ajoutez le DVC signé au dossier avant de valider le lead.',
            ]);
        }

        // Payments are independent of the pipeline: changing status never
        // touches them. Validé only records when the lead was validated, and
        // can set the contract total when no payment gave it yet.
        $changes = ['status' => $status->value];

        if ($status === LeadStatusEnum::VALIDE && $fromStatus !== LeadStatusEnum::VALIDE) {
            $changes['validated_at'] = now();
        } elseif ($fromStatus === LeadStatusEnum::VALIDE && $status !== LeadStatusEnum::VALIDE) {
            $changes['validated_at'] = null;
        }

        if ($expectedRevenue !== null && $lead->expected_revenue === null) {
            $changes['expected_revenue'] = $expectedRevenue;
            $changes['payment_status'] = $lead->payment_status?->value ?? PaymentStatusEnum::NON_PAYE->value;
        }

        $lead->update($changes);

        $lead->statusHistories()->create([
            'from_status' => $fromStatus,
            'to_status' => $status->value,
            'changed_by' => $changedBy->id,
            'comment' => $comment,
            'meta' => $meta,
        ]);

        if ($status === LeadStatusEnum::GESTION && $fromStatus !== LeadStatusEnum::GESTION) {
            app(GestionService::class)->assignToGestion($lead, $changedBy);
        }

        return $lead->refresh()->load(['assignedAgent', 'gestionAssignedAgent', 'team', 'leadSource', 'creator']);
    }

    /**
     * Apply one action to several leads. Each lead is authorized and applied
     * on its own, so one refusal (policy, doublon, payments…) does not undo
     * the others; the result says what happened to every id.
     *
     * @param  array<int, int>  $ids
     * @return array{done: int, failed: int, results: array<int, array{id: int, ok: bool, error: ?string}>}
     */
    public function bulk(User $user, array $ids, string $action, array $data): array
    {
        $ability = match ($action) {
            'assign' => 'assign',
            'status' => 'updateStatus',
            'delete' => 'delete',
        };

        $leads = Lead::whereIn('id', $ids)->get()->keyBy('id');
        $results = [];

        foreach ($ids as $id) {
            $lead = $leads->get($id);

            if (! $lead || ! $user->can($ability, $lead)) {
                $results[] = ['id' => $id, 'ok' => false, 'error' => $lead ? 'Action non autorisée sur ce lead.' : 'Lead introuvable.'];

                continue;
            }

            try {
                DB::transaction(fn () => match ($action) {
                    'assign' => $this->assign($lead, (int) $data['assigned_to'], $user),
                    'status' => $this->updateStatus($lead, LeadStatusEnum::from($data['status']), $user, $data['comment'] ?? null),
                    'delete' => $this->deleteLead($lead),
                });
                $results[] = ['id' => $id, 'ok' => true, 'error' => null];
            } catch (ValidationException $e) {
                $results[] = ['id' => $id, 'ok' => false, 'error' => collect($e->errors())->flatten()->first()];
            }
        }

        $done = count(array_filter($results, fn ($r) => $r['ok']));

        return ['done' => $done, 'failed' => count($results) - $done, 'results' => $results];
    }

    public function addNote(Lead $lead, User $user, string $note): LeadNote
    {
        return $lead->notes()->create([
            'user_id' => $user->id,
            'note' => $note,
        ]);
    }

    public function logCall(Lead $lead, User $user, ?string $outcome = null, ?string $note = null): LeadCall
    {
        return $lead->calls()->create([
            'user_id' => $user->id,
            'outcome' => $outcome,
            'note' => $note,
        ]);
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
