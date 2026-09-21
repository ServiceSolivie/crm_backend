<?php

namespace App\Services;

use App\Enums\AppointmentStatusEnum;
use App\Enums\LeadStatusEnum;
use App\Enums\PermissionEnum;
use App\Filters\LeadFilter;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Read-side helpers for the redesigned screens: quick search, counters,
 * duplicate check, activity feed, previous/next navigation and the
 * "Ma journée" agenda. Every query is limited to what the user may see.
 */
class LeadWorkspaceService
{
    public function __construct(
        protected LeadService $leads,
        protected AppointmentService $appointments,
    ) {}

    /**
     * Leads the user may see.
     */
    protected function visibleLeads(User $user): Builder
    {
        $query = Lead::query();
        ($this->leads->visibilityScope($user))($query);

        return $query;
    }

    /**
     * Appointments the user may see.
     */
    protected function visibleAppointments(User $user): Builder
    {
        $query = Appointment::query();
        ($this->appointments->visibilityScope($user))($query);

        return $query;
    }

    /**
     * Quick search across name, phone, e-mail and reference (command palette).
     */
    public function search(User $user, string $term, int $limit = 8): Collection
    {
        $term = trim($term);
        $digits = preg_replace('/\D+/', '', $term);

        return $this->visibleLeads($user)
            ->where(function (Builder $q) use ($term, $digits) {
                $q->where('reference', 'like', "%{$term}%")
                    ->orWhere('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$term}%"])
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%");

                // "06 12 34" should find "0612345678"
                if (strlen($digits) >= 4) {
                    $q->orWhereRaw($this->phoneDigitsSql().' LIKE ?', ["%{$digits}%"]);
                }
            })
            ->with('assignedAgent:id,name')
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Counts for the leads list tabs, with the same scope as the list.
     *
     * @return array<string, int>
     */
    public function counts(User $user): array
    {
        $base = fn () => $this->visibleLeads($user);

        return [
            'all' => $base()->count(),
            'mine' => $base()->where('assigned_to', $user->id)->count(),
            'unassigned' => $base()->whereNull('assigned_to')->count(),
            'doublons' => $base()->where('is_doublon', true)->count(),
            'due_today' => $base()->whereHas('appointments', fn (Builder $q) => $q
                ->whereIn('status', AppointmentStatusEnum::openValues())
                ->where('scheduled_at', '<=', now()->endOfDay()))->count(),
        ];
    }

    /**
     * Sidebar counters for the signed-in user.
     *
     * @return array<string, int>
     */
    public function counters(User $user): array
    {
        $open = fn () => $this->visibleAppointments($user)->whereIn('status', AppointmentStatusEnum::openValues());

        return [
            'leads_total' => $this->visibleLeads($user)->count(),
            'appointments_overdue' => $open()->where('scheduled_at', '<', now())->count(),
            'appointments_today' => $open()->whereBetween('scheduled_at', [now(), now()->endOfDay()])->count(),
            'follow_ups_due' => $this->counts($user)['due_today'],
        ];
    }

    /**
     * Possible duplicates of a contact being typed in the lead form. Matches
     * the same phone (last 9 digits, ignoring spaces/dots) or the same e-mail
     * across all leads; leads the user cannot open come back without contact
     * details or id.
     *
     * @return array<int, array<string, mixed>>
     */
    public function duplicates(User $user, ?string $phone, ?string $email, ?int $excludeId = null): array
    {
        $digits = substr(preg_replace('/\D+/', '', (string) $phone), -9);
        $email = trim((string) $email);

        if (strlen($digits) < 8 && $email === '') {
            return [];
        }

        $matches = Lead::query()
            ->when($excludeId, fn (Builder $q) => $q->where('id', '!=', $excludeId))
            ->where(function (Builder $q) use ($digits, $email) {
                if (strlen($digits) >= 8) {
                    $q->orWhereRaw('RIGHT('.$this->phoneDigitsSql().', 9) = ?', [$digits]);
                }
                if ($email !== '') {
                    $q->orWhere('email', $email);
                }
            })
            ->with('assignedAgent:id,name')
            ->latest()
            ->limit(5)
            ->get();

        $visibleIds = $this->visibleLeads($user)->whereIn('id', $matches->pluck('id'))->pluck('id')->all();

        return $matches->map(function (Lead $lead) use ($visibleIds) {
            $canOpen = in_array($lead->id, $visibleIds, true);

            return [
                'id' => $canOpen ? $lead->id : null,
                'can_open' => $canOpen,
                'reference' => $lead->reference,
                'name' => trim("{$lead->first_name} {$lead->last_name}"),
                'status' => $lead->status->value,
                'insurance_type' => $lead->insurance_type?->value,
                'assigned_agent' => $lead->assignedAgent ? ['id' => $lead->assignedAgent->id, 'name' => $lead->assignedAgent->name] : null,
                'created_at' => $lead->created_at?->toIso8601String(),
            ];
        })->all();
    }

    /**
     * Everything that happened on a lead, newest first, in one paginated list.
     */
    public function activity(Lead $lead, User $user, int $page = 1, int $perPage = 30, ?string $type = null): LengthAwarePaginator
    {
        $person = fn (?User $u) => $u ? ['id' => $u->id, 'name' => $u->name] : null;
        $items = collect();

        foreach ($lead->notes()->with('user')->get() as $note) {
            $items->push(['type' => 'note', 'id' => $note->id, 'at' => $note->created_at, 'user' => $person($note->user),
                'data' => ['note' => $note->note]]);
        }

        foreach ($lead->calls()->with('user')->get() as $call) {
            $items->push(['type' => 'call', 'id' => $call->id, 'at' => $call->created_at, 'user' => $person($call->user),
                'data' => ['outcome' => $call->outcome, 'note' => $call->note]]);
        }

        foreach ($lead->statusHistories()->with('changedBy')->get() as $h) {
            $items->push(['type' => 'status', 'id' => $h->id, 'at' => $h->created_at, 'user' => $person($h->changedBy),
                'data' => [
                    'from' => $h->from_status?->value,
                    'to' => $h->to_status?->value,
                    'comment' => $h->comment,
                    'meta' => $h->meta,
                ]]);
        }

        foreach ($lead->assignmentHistories()->with(['fromUser', 'toUser', 'assignedBy'])->get() as $h) {
            $items->push(['type' => 'assignment', 'id' => $h->id, 'at' => $h->created_at, 'user' => $person($h->assignedBy),
                'data' => [
                    'from' => $person($h->fromUser) ?? ($h->from_agent_name ? ['id' => null, 'name' => $h->from_agent_name] : null),
                    'to' => $person($h->toUser),
                ]]);
        }

        foreach ($lead->appointments()->with(['agent', 'creator'])->get() as $a) {
            $items->push(['type' => 'appointment', 'id' => $a->id, 'at' => $a->created_at, 'user' => $person($a->creator),
                'data' => [
                    'scheduled_at' => $a->scheduled_at?->toIso8601String(),
                    'duration_minutes' => $a->duration_minutes,
                    'status' => $a->status->value,
                    'location' => $a->location,
                    'notes' => $a->notes,
                    'agent' => $person($a->agent),
                ]]);
        }

        if ($user->can(PermissionEnum::PAYMENTS_VIEW->value)) {
            foreach ($lead->payments()->with('creator')->get() as $p) {
                $items->push(['type' => 'payment', 'id' => $p->id, 'at' => $p->created_at, 'user' => $person($p->creator),
                    'data' => [
                        'amount' => $p->amount,
                        'payment_date' => $p->payment_date?->toDateString(),
                        'status' => $p->status?->value,
                        'payment_method' => $p->payment_method?->value,
                        'custom_payment_method' => $p->custom_payment_method,
                        'reference_number' => $p->reference_number,
                        'notes' => $p->notes,
                    ]]);
            }
        }

        if ($user->can(PermissionEnum::DOCUMENTS_VIEW->value)) {
            $typeLabels = \App\Models\DocumentType::query()->pluck('label', 'name');

            foreach ($lead->documents()->with('uploader')->get() as $d) {
                $items->push(['type' => 'document', 'id' => $d->id, 'at' => $d->created_at, 'user' => $person($d->uploader),
                    'data' => [
                        'document_type' => $d->document_type,
                        'document_label' => $typeLabels[$d->document_type] ?? $d->document_type,
                        'original_filename' => $d->original_filename,
                    ]]);
            }
        }

        // "status" also covers reassignments (both are pipeline changes in the feed)
        if ($type) {
            $types = $type === 'status' ? ['status', 'assignment'] : [$type];
            $items = $items->filter(fn ($i) => in_array($i['type'], $types, true));
        }

        $sorted = $items
            ->sortByDesc(fn ($i) => [$i['at']?->getTimestamp() ?? 0, $i['id']])
            ->values()
            ->map(fn ($i) => [...$i, 'at' => $i['at']?->toIso8601String()]);

        return new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
        );
    }

    /**
     * Previous / next lead in the list the user came from (same filters and sort).
     *
     * @return array{prev_id: ?int, next_id: ?int, position: ?int, total: int}
     */
    public function neighbours(Lead $lead, User $user, LeadFilter $filters): array
    {
        $query = $this->visibleLeads($user)->filter($filters);

        // Same tie-break as the paginated list, so the order is stable
        $ids = $query->orderBy('leads.id')->pluck('leads.id')->all();
        $index = array_search($lead->id, $ids, true);

        return [
            'prev_id' => $index !== false && $index > 0 ? $ids[$index - 1] : null,
            'next_id' => $index !== false && $index < count($ids) - 1 ? $ids[$index + 1] : null,
            'position' => $index !== false ? $index + 1 : null,
            'total' => count($ids),
        ];
    }

    /**
     * "Ma journée": late appointments, the day's appointments and the leads
     * waiting on the user (dossiers sent back by gestion).
     *
     * @return array<string, mixed>
     */
    public function agenda(User $user, ?string $date = null): array
    {
        $day = $date ? Carbon::parse($date) : Carbon::today();
        $with = ['lead:id,reference,first_name,last_name,phone,insurance_type,status', 'agent:id,name'];

        $overdue = $this->visibleAppointments($user)
            ->whereIn('status', AppointmentStatusEnum::openValues())
            ->where('scheduled_at', '<', $day->isToday() ? now() : $day->copy()->startOfDay())
            ->with($with)
            ->orderBy('scheduled_at')
            ->limit(50)
            ->get();

        $today = $this->visibleAppointments($user)
            ->whereBetween('scheduled_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->whereNotIn('id', $overdue->pluck('id'))
            ->with($with)
            ->orderBy('scheduled_at')
            ->get();

        // Dossiers to fix: only the agent who owns the lead has to act
        $tasks = Lead::query()
            ->where('assigned_to', $user->id)
            ->where('status', LeadStatusEnum::A_CORRIGER->value)
            ->with('lastFlag')
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->map(fn (Lead $lead) => [
                'type' => 'missing_document',
                'lead' => [
                    'id' => $lead->id,
                    'reference' => $lead->reference,
                    'first_name' => $lead->first_name,
                    'last_name' => $lead->last_name,
                    'insurance_type' => $lead->insurance_type?->value,
                ],
                'documents' => $lead->lastFlag?->meta['missing_documents'] ?? [],
                'label' => $lead->lastFlag?->comment,
                'at' => $lead->lastFlag?->created_at?->toIso8601String(),
            ])
            ->values();

        return [
            'date' => $day->toDateString(),
            'overdue' => $overdue,
            'today' => $today,
            'tasks' => $tasks,
            'counts' => [
                'overdue' => $overdue->count(),
                'today' => $today->count(),
                'today_open' => $today->filter(fn (Appointment $a) => in_array($a->status->value, AppointmentStatusEnum::openValues(), true))->count(),
                'tasks' => $tasks->count(),
            ],
        ];
    }

    /**
     * SQL expression: the phone column with spaces, dots, dashes, plus signs
     * and brackets removed.
     */
    protected function phoneDigitsSql(): string
    {
        return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '.', ''), '-', ''), '+', ''), '(', ''), ')', '')";
    }
}
