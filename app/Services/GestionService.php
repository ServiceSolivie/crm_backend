<?php

namespace App\Services;

use App\Enums\LeadStatusEnum;
use App\Enums\RoleEnum;
use App\Mail\LeadAssignedToGestionMail;
use App\Mail\LeadNeedsCorrectionMail;
use App\Models\Lead;
use App\Models\LeadStatusHistory;
use App\Models\User;
use App\Notifications\LeadNeedsCorrectionNotification;
use App\Notifications\LeadReceivedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class GestionService
{
    /**
     * Assign a lead to whichever gestion user currently has the fewest
     * open GESTION leads (round-robin by load, not by a rotating
     * cursor) - with a single gestion user today this trivially always
     * picks her; it naturally balances once more are added.
     */
    public function assignToGestion(Lead $lead, ?User $sender = null): User
    {
        $gestionUser = User::role(RoleEnum::GESTION->value)
            ->withCount(['gestionLeads as open_gestion_count' => function ($query) {
                $query->where('status', LeadStatusEnum::GESTION->value);
            }])
            ->orderBy('open_gestion_count')
            ->firstOrFail();

        $lead->update(['gestion_assigned_to' => $gestionUser->id]);

        $gestionUser->notify(new LeadReceivedNotification($lead));

        // SMTP credentials may not be configured yet - the in-app
        // notification above is the reliable channel; email is best-effort
        // on top of it, so a mail failure shouldn't block the assignment.
        try {
            Mail::to($gestionUser->email)->send(new LeadAssignedToGestionMail($lead, $sender));
        } catch (\Throwable $e) {
            report($e);
        }

        return $gestionUser;
    }

    /**
     * Flag issues on a lead, moving it to A_CORRIGER and notifying/emailing
     * the assigned agent (not the gestion user) about exactly what's wrong.
     * `gestion_assigned_to` is left untouched - updateStatus() never
     * touches it - so once the agent fixes things and sends it back to
     * GESTION, it naturally returns to the same reviewer.
     *
     * @param  array<int, string>  $issueTypes  subset of documents_manquants / information_manquante / information_incorrecte
     * @param  array<int, string>|null  $missingDocuments  document type labels, only relevant when documents_manquants is flagged
     */
    public function flagIssue(Lead $lead, User $gestionUser, array $issueTypes, ?array $missingDocuments, ?string $comment): Lead
    {
        $parts = [];

        if (in_array('documents_manquants', $issueTypes, true)) {
            $parts[] = 'Documents manquants'.($missingDocuments ? ' ('.implode(', ', $missingDocuments).')' : '');
        }
        if (in_array('information_manquante', $issueTypes, true)) {
            $parts[] = 'Information manquante'.($comment ? " : {$comment}" : '');
        }
        if (in_array('information_incorrecte', $issueTypes, true)) {
            $parts[] = 'Information incorrecte'.($comment ? " : {$comment}" : '');
        }

        $updated = app(LeadService::class)->updateStatus(
            $lead,
            LeadStatusEnum::A_CORRIGER,
            $gestionUser,
            implode(' ; ', $parts),
            null,
            [
                'issue_types' => array_values($issueTypes),
                'missing_documents' => array_values($missingDocuments ?? []),
                'comment' => $comment,
            ],
        );

        $agent = $updated->assignedAgent;
        if ($agent) {
            $agent->notify(new LeadNeedsCorrectionNotification($updated, $parts));

            // SMTP credentials may not be configured yet - the in-app
            // notification above is the reliable channel; email is
            // best-effort on top of it, so a mail failure shouldn't roll
            // back the status change or block the response.
            try {
                Mail::to($agent->email)->send(new LeadNeedsCorrectionMail($updated, $parts));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $updated;
    }

    /**
     * @return array{backlog_count: int, stuck_count: int, stuck_leads: Collection, waiting_on_agent_count: int, validated_this_week: int, avg_hours_in_gestion: ?float, call2_ko_count: int, awaiting_pdg_count: int, pdg_ko_count: int, pdg_ok_count: int}
     */
    public function dashboardStats(User $user): array
    {
        $baseQuery = fn () => Lead::where('gestion_assigned_to', $user->id);

        $stuckLeads = $baseQuery()
            ->whereIn('status', [LeadStatusEnum::CALL2_KO->value, LeadStatusEnum::PDG_KO->value])
            ->with(['assignedAgent'])
            ->get();

        return [
            'backlog_count' => $baseQuery()->where('status', LeadStatusEnum::GESTION->value)->count(),
            'stuck_count' => $stuckLeads->count(),
            'stuck_leads' => $stuckLeads,
            'waiting_on_agent_count' => $baseQuery()->where('status', LeadStatusEnum::A_CORRIGER->value)->count(),
            // Leads this user validated in the last 7 days, each counted once
            // (Validé is the final step; PDG OK comes before it).
            'validated_this_week' => LeadStatusHistory::where('to_status', LeadStatusEnum::VALIDE->value)
                ->where('changed_by', $user->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->distinct()
                ->count('lead_id'),
            'avg_hours_in_gestion' => $this->averageHoursInGestion($user),
            // Call2/PDG checkpoint breakdown - current status snapshot, not
            // historical attempt counts, consistent with the rest of this
            // dashboard being "what needs attention right now".
            'call2_ko_count' => $baseQuery()->where('status', LeadStatusEnum::CALL2_KO->value)->count(),
            'awaiting_pdg_count' => $baseQuery()->where('status', LeadStatusEnum::CALL2_OK->value)->count(),
            'pdg_ko_count' => $baseQuery()->where('status', LeadStatusEnum::PDG_KO->value)->count(),
            // PDG_OK doesn't auto-advance to VALIDE yet (that transition
            // logic hasn't been built) - this surfaces leads sitting here
            // so it's visible that they still need a manual final step.
            'pdg_ok_count' => $baseQuery()->where('status', LeadStatusEnum::PDG_OK->value)->count(),
            'queue' => $this->workQueue($user),
        ];
    }

    /**
     * Statuses a lead goes through while it is with gestion.
     *
     * @return array<int, string>
     */
    public static function activeStatuses(): array
    {
        return array_map(fn (LeadStatusEnum $s) => $s->value, [
            LeadStatusEnum::GESTION,
            LeadStatusEnum::A_CORRIGER,
            LeadStatusEnum::CALL2_OK,
            LeadStatusEnum::CALL2_KO,
            LeadStatusEnum::PDG_OK,
            LeadStatusEnum::PDG_KO,
        ]);
    }

    /**
     * Every lead currently with this gestion user, oldest in its status
     * first, with what the dashboard needs to decide the next step
     * (status, DVC, agent, time in the current status).
     *
     * @return array<int, array<string, mixed>>
     */
    protected function workQueue(User $user): array
    {
        $leads = Lead::where('gestion_assigned_to', $user->id)
            ->whereIn('status', self::activeStatuses())
            ->with('assignedAgent:id,name')
            ->addSelect([
                'status_since' => LeadStatusHistory::select('created_at')
                    ->whereColumn('lead_id', 'leads.id')
                    ->latest('created_at')
                    ->latest('id')
                    ->limit(1),
            ])
            ->get();

        return $leads
            ->map(fn (Lead $lead) => [
                'id' => $lead->id,
                'reference' => $lead->reference,
                'first_name' => $lead->first_name,
                'last_name' => $lead->last_name,
                'phone' => $lead->phone,
                'insurance_type' => $lead->insurance_type?->value,
                'status' => $lead->status->value,
                'dvc_status' => $lead->dvc_status?->value,
                'agent' => $lead->assignedAgent ? ['id' => $lead->assignedAgent->id, 'name' => $lead->assignedAgent->name] : null,
                'status_since' => $lead->status_since ? \Illuminate\Support\Carbon::parse($lead->status_since)->toIso8601String() : $lead->updated_at?->toIso8601String(),
            ])
            ->sortBy('status_since')
            ->values()
            ->all();
    }

    /**
     * Averages, per lead, the time between entering GESTION status and the
     * next status change - no existing time-in-status helper anywhere in
     * the app, so this pairs consecutive lead_status_histories rows
     * directly. Leads still sitting in GESTION (no exit row yet) are
     * excluded from the average, not counted as zero.
     */
    protected function averageHoursInGestion(User $user): ?float
    {
        $leadIds = Lead::where('gestion_assigned_to', $user->id)->pluck('id');

        if ($leadIds->isEmpty()) {
            return null;
        }

        $durations = [];

        $histories = LeadStatusHistory::whereIn('lead_id', $leadIds)
            ->orderBy('lead_id')
            ->orderBy('created_at')
            ->get(['lead_id', 'to_status', 'created_at'])
            ->groupBy('lead_id');

        foreach ($histories as $rows) {
            $rows = $rows->values();

            foreach ($rows as $i => $row) {
                if ($row->to_status === LeadStatusEnum::GESTION && isset($rows[$i + 1])) {
                    $durations[] = $row->created_at->diffInHours($rows[$i + 1]->created_at);
                }
            }
        }

        return $durations ? round(array_sum($durations) / count($durations), 1) : null;
    }
}
