<?php

namespace App\Repositories\Eloquent;

use App\Enums\AppointmentStatusEnum;
use App\Enums\CallStatusEnum;
use App\Enums\LeadStatusEnum;
use App\Enums\PaymentRecordStatusEnum;
use App\Enums\PaymentStatusEnum;
use App\Enums\RoleEnum;
use App\Filters\AppointmentFilter;
use App\Filters\LeadFilter;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use App\Repositories\Contracts\ReportRepositoryInterface;
use App\Support\LeadSourceScope;
use App\Support\LeadStatusCatalog;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportRepository implements ReportRepositoryInterface
{
    public function paginateTeamReport(?Closure $scope, ?int $teamId, ?string $from, ?string $to, int $perPage, ?array $sourceIds = null): LengthAwarePaginator
    {
        $query = Team::query()
            ->with('manager')
            ->withCount('members')
            ->withCount(['leads as total_leads' => function (Builder $query) use ($from, $to, $sourceIds) {
                LeadSourceScope::leads($query, $sourceIds);
                $this->applyDateRange($query, 'created_at', $from, $to);
            }])
            ->withCount(['leads as validated_leads' => function (Builder $query) use ($from, $to, $sourceIds) {
                LeadSourceScope::leads($query, $sourceIds);
                $query->where('status', LeadStatusEnum::VALIDE->value);
                $this->applyDateRange($query, 'created_at', $from, $to);
            }])
            ->withCount(['appointments as total_appointments' => function (Builder $query) use ($from, $to, $sourceIds) {
                LeadSourceScope::leads($query, $sourceIds, 'leads.lead_source_id');
                $this->applyDateRange($query, 'scheduled_at', $from, $to);
            }])
            ->withCount(['appointments as completed_appointments' => function (Builder $query) use ($from, $to, $sourceIds) {
                LeadSourceScope::leads($query, $sourceIds, 'leads.lead_source_id');
                $query->where('appointments.status', AppointmentStatusEnum::REALISE->value);
                $this->applyDateRange($query, 'scheduled_at', $from, $to);
            }])
            ->withCount(['phoneCalls as total_calls' => function (Builder $query) use ($from, $to, $sourceIds) {
                LeadSourceScope::viaLead($query, $sourceIds);
                $this->realCalls($query, $from, $to);
            }])
            ->withSum(['payments as revenue_received' => function (Builder $query) use ($from, $to, $sourceIds) {
                LeadSourceScope::leads($query, $sourceIds, 'leads.lead_source_id');
                $query->where('payments.status', PaymentRecordStatusEnum::REUSSI->value);
                $this->applyDateRange($query, 'payments.payment_date', $from, $to);
            }], 'amount');

        if ($teamId) {
            $query->where('teams.id', $teamId);
        }

        if ($scope) {
            $scope($query);
        }

        return $query->paginate($perPage);
    }

    public function paginateAgentReport(?Closure $scope, ?int $teamId, ?string $from, ?string $to, int $perPage, ?array $sourceIds = null): LengthAwarePaginator
    {
        $query = User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('name', RoleEnum::AGENT->value))
            ->with('team')
            ->withCount(['assignedLeads as total_leads' => function (Builder $query) use ($from, $to, $sourceIds) {
                LeadSourceScope::leads($query, $sourceIds);
                $this->applyDateRange($query, 'created_at', $from, $to);
            }])
            ->withCount(['assignedLeads as validated_leads' => function (Builder $query) use ($from, $to, $sourceIds) {
                LeadSourceScope::leads($query, $sourceIds);
                $query->where('status', LeadStatusEnum::VALIDE->value);
                $this->applyDateRange($query, 'created_at', $from, $to);
            }])
            ->withCount(['appointments as total_appointments' => function (Builder $query) use ($from, $to, $sourceIds) {
                LeadSourceScope::viaLead($query, $sourceIds);
                $this->applyDateRange($query, 'scheduled_at', $from, $to);
            }])
            ->withCount(['appointments as completed_appointments' => function (Builder $query) use ($from, $to, $sourceIds) {
                LeadSourceScope::viaLead($query, $sourceIds);
                $query->where('status', AppointmentStatusEnum::REALISE->value);
                $this->applyDateRange($query, 'scheduled_at', $from, $to);
            }])
            ->withCount(['phoneCalls as total_phone_calls' => function (Builder $query) use ($from, $to, $sourceIds) {
                LeadSourceScope::viaLead($query, $sourceIds);
                $this->realCalls($query, $from, $to);
            }])
            ->withCount(['phoneCalls as answered_phone_calls' => function (Builder $query) use ($from, $to, $sourceIds) {
                LeadSourceScope::viaLead($query, $sourceIds);
                $this->realCalls($query, $from, $to);
                $query->where('status', CallStatusEnum::COMPLETED->value);
            }])
            ->withSum(['phoneCalls as talk_seconds' => function (Builder $query) use ($from, $to, $sourceIds) {
                LeadSourceScope::viaLead($query, $sourceIds);
                $this->realCalls($query, $from, $to);
                $query->where('status', CallStatusEnum::COMPLETED->value);
            }], 'talk_seconds')
            ->withSum(['assignedLeadPayments as revenue_received' => function (Builder $query) use ($from, $to, $sourceIds) {
                LeadSourceScope::leads($query, $sourceIds, 'leads.lead_source_id');
                $query->where('payments.status', PaymentRecordStatusEnum::REUSSI->value);
                $this->applyDateRange($query, 'payments.payment_date', $from, $to);
            }], 'amount');

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        if ($scope) {
            $scope($query);
        }

        return $query->paginate($perPage);
    }

    public function paginateConversionReport(string $groupBy, ?Closure $scope, ?int $teamId, ?string $from, ?string $to, int $perPage): LengthAwarePaginator
    {
        $query = Lead::query()->select([]);

        match ($groupBy) {
            'team' => $query->join('teams', 'teams.id', '=', 'leads.team_id')
                ->selectRaw('leads.team_id as dimension_id, teams.name as dimension_name')
                ->whereNotNull('leads.team_id')
                ->groupBy('leads.team_id', 'teams.name'),

            'agent' => $query->join('users', 'users.id', '=', 'leads.assigned_to')
                ->selectRaw('leads.assigned_to as dimension_id, users.name as dimension_name')
                ->whereNotNull('leads.assigned_to')
                ->groupBy('leads.assigned_to', 'users.name'),

            'insurance_type' => $query->selectRaw('leads.insurance_type as dimension_id')
                ->whereNotNull('leads.insurance_type')
                ->groupBy('leads.insurance_type'),

            default => $query->join('lead_sources', 'lead_sources.id', '=', 'leads.lead_source_id')
                ->selectRaw('leads.lead_source_id as dimension_id, lead_sources.name as dimension_name')
                ->whereNotNull('leads.lead_source_id')
                ->groupBy('leads.lead_source_id', 'lead_sources.name'),
        };

        $query->selectRaw('count(*) as total')
            ->selectRaw('sum(case when leads.status = ? then 1 else 0 end) as validated', [LeadStatusEnum::VALIDE->value]);

        $this->applyDateRange($query, 'leads.created_at', $from, $to);

        if ($teamId) {
            $query->where('leads.team_id', $teamId);
        }

        if ($scope) {
            $scope($query);
        }

        return $query->orderByDesc('total')->paginate($perPage);
    }

    public function paginateRevenueReport(?Closure $scope, ?string $paymentStatus, ?int $teamId, ?int $agentId, ?string $from, ?string $to, int $perPage): LengthAwarePaginator
    {
        $query = $this->revenueBaseQuery($scope, $paymentStatus, $teamId, $agentId, $from, $to)
            ->with(['assignedAgent:id,name', 'team:id,name'])
            ->withSum(['payments' => fn (Builder $query) => $query->where('payments.status', PaymentRecordStatusEnum::REUSSI->value)], 'amount')
            ->withCount('payments')
            ->orderByDesc('validated_at');

        return $query->paginate($perPage);
    }

    public function revenueSummary(?Closure $scope, ?string $paymentStatus, ?int $teamId, ?int $agentId, ?string $from, ?string $to): array
    {
        $query = $this->revenueBaseQuery($scope, $paymentStatus, $teamId, $agentId, $from, $to);

        $totalExpected = (clone $query)->sum('expected_revenue');
        $leadsCount = (clone $query)->count();

        $byStatus = (clone $query)
            ->selectRaw('payment_status, count(*) as aggregate')
            ->groupBy('payment_status')
            ->pluck('aggregate', 'payment_status');

        $leadIds = (clone $query)->select('id');
        $totalReceived = Payment::received()->whereIn('lead_id', $leadIds)->sum('amount');

        return [
            'total_expected' => round((float) $totalExpected, 2),
            'total_received' => round((float) $totalReceived, 2),
            'total_remaining' => round((float) bcsub((string) $totalExpected, (string) $totalReceived, 2), 2),
            'leads_count' => $leadsCount,
            'fully_paid' => (int) ($byStatus[PaymentStatusEnum::PAYE->value] ?? 0),
            'partially_paid' => (int) ($byStatus[PaymentStatusEnum::PARTIELLEMENT_PAYE->value] ?? 0),
            'unpaid' => (int) ($byStatus[PaymentStatusEnum::NON_PAYE->value] ?? 0),
        ];
    }

    public function leadSummary(?Closure $scope, LeadFilter $filters): array
    {
        $query = Lead::query();
        if ($scope) {
            $scope($query);
        }
        $query->filter($filters);

        $byStatus = (clone $query)->reorder()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($n) => (int) $n);

        // Custom statuses count in the stage they were placed in
        $catalog = app(LeadStatusCatalog::class);
        $byStage = [];
        foreach ($catalog->codes() as $code) {
            $stage = $catalog->stage($code);
            $byStage[$stage] = ($byStage[$stage] ?? 0) + ($byStatus[$code] ?? 0);
        }

        $total = (int) $byStatus->sum();
        $validated = (int) ($byStatus[LeadStatusEnum::VALIDE->value] ?? 0);

        return [
            'total' => $total,
            'validated' => $validated,
            'conversion_rate' => $total > 0 ? round($validated / $total * 100, 2) : 0.0,
            'unassigned' => (clone $query)->reorder()->whereNull('assigned_to')->count(),
            'by_status' => $byStatus,
            'by_stage' => $byStage,
        ];
    }

    public function appointmentSummary(?Closure $scope, AppointmentFilter $filters): array
    {
        $query = Appointment::query();
        if ($scope) {
            $scope($query);
        }
        $query->filter($filters);

        $byStatus = (clone $query)->reorder()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($n) => (int) $n);

        $total = (int) $byStatus->sum();
        $done = (int) ($byStatus[AppointmentStatusEnum::REALISE->value] ?? 0);

        return [
            'total' => $total,
            'planned' => (int) ($byStatus[AppointmentStatusEnum::PLANIFIE->value] ?? 0) + (int) ($byStatus[AppointmentStatusEnum::CONFIRME->value] ?? 0),
            'completed' => $done,
            'cancelled' => (int) ($byStatus[AppointmentStatusEnum::ANNULE->value] ?? 0),
            'no_show' => (int) ($byStatus[AppointmentStatusEnum::NON_VENU->value] ?? 0),
            'rescheduled' => (int) ($byStatus[AppointmentStatusEnum::REPORTE->value] ?? 0),
            'completion_rate' => $total > 0 ? round($done / $total * 100, 2) : 0.0,
            'overdue' => (clone $query)->reorder()
                ->whereIn('status', AppointmentStatusEnum::openValues())
                ->where('scheduled_at', '<', now())
                ->count(),
            'by_status' => $byStatus,
        ];
    }

    /**
     * Figures for one agent, over the leads currently assigned to him:
     *  - received: successful payments dated in the period (cash in);
     *  - expected, remaining, collection rate: the leads he got validated in
     *    the period, with every payment they have received so far;
     *  - months: the same month by month, plus leads received and conversion;
     *  - top clients: most cash in over the period;
     *  - to follow up: his validated leads (any date) still owing money.
     */
    public function agentRevenue(User $agent, CarbonImmutable $from, CarbonImmutable $to, ?array $sourceIds = null, ?array $rankAmong = null): array
    {
        $start = $from->startOfDay();
        $end = $to->endOfDay();
        $agentLeads = fn () => LeadSourceScope::leads(Lead::query()->where('leads.assigned_to', $agent->id), $sourceIds, 'leads.lead_source_id');

        $cash = $this->receivedPayments($agentLeads(), $start, $end)->get(['payments.lead_id', 'payments.amount', 'payments.payment_date']);

        // Same length, just before: for the "vs previous period" hint
        $days = (int) $start->diffInDays($end->startOfDay()) + 1;
        $previousEnd = $start->subDay()->endOfDay();
        $previousReceived = (float) $this->receivedPayments($agentLeads(), $previousEnd->subDays($days - 1)->startOfDay(), $previousEnd)->sum('payments.amount');

        $validated = $agentLeads()
            ->where('leads.status', LeadStatusEnum::VALIDE->value)
            ->whereBetween('leads.validated_at', [$start, $end])
            ->withSum(['payments as received_sum' => fn (Builder $query) => $query->received()], 'amount')
            ->get(['leads.id', 'leads.expected_revenue', 'leads.validated_at'])
            ->map(fn (Lead $lead) => [
                'month' => $lead->validated_at->format('Y-m'),
                'expected' => (float) $lead->expected_revenue,
                'received' => (float) $lead->received_sum,
                'remaining' => max(0, (float) $lead->expected_revenue - (float) $lead->received_sum),
            ]);

        $arrivals = $agentLeads()->whereBetween('leads.created_at', [$start, $end])->pluck('leads.created_at')
            ->countBy(fn ($date) => CarbonImmutable::parse($date)->format('Y-m'));
        $cashByMonth = $cash->groupBy(fn (Payment $payment) => CarbonImmutable::parse($payment->payment_date)->format('Y-m'))
            ->map(fn ($payments) => (float) $payments->sum('amount'));
        $validatedByMonth = $validated->groupBy('month');

        $months = [];
        for ($month = $start->startOfMonth(); $month->lte($end); $month = $month->addMonth()) {
            $key = $month->format('Y-m');
            $cohort = $validatedByMonth->get($key, collect());
            $leads = (int) ($arrivals[$key] ?? 0);
            $months[] = [
                'month' => $key,
                'leads' => $leads,
                'validated' => $cohort->count(),
                'expected' => round($cohort->sum('expected'), 2),
                'received' => round($cashByMonth[$key] ?? 0, 2),
                'remaining' => round($cohort->sum('remaining'), 2),
                'conversion_rate' => $leads > 0 ? round($cohort->count() / $leads * 100, 1) : null,
            ];
        }

        $expected = $validated->sum('expected');
        $received = (float) $cash->sum('amount');

        return [
            'kpis' => [
                'received' => round($received, 2),
                'previous_received' => round($previousReceived, 2),
                'expected' => round($expected, 2),
                'remaining' => round($validated->sum('remaining'), 2),
                'validated_leads' => $validated->count(),
                'clients_owing' => $validated->where('remaining', '>', 0.005)->count(),
                'collection_rate' => $expected > 0 ? round(min(100, $validated->sum('received') / $expected * 100), 1) : null,
                'average_basket' => $validated->count() > 0 ? round($expected / $validated->count(), 2) : null,
            ],
            'months' => $months,
            'top_clients' => $this->topClients($cash),
            'to_follow_up' => $this->clientsOwing($agentLeads()),
            'ranking' => $rankAmong === null ? null : $this->revenueRanking($rankAmong, $start, $end, $sourceIds),
        ];
    }

    /**
     * Successful payments dated in the period, on the given leads.
     */
    protected function receivedPayments(Builder $leads, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return Payment::query()->received()
            ->whereIn('payments.lead_id', $leads->select('leads.id'))
            ->whereBetween('payments.payment_date', [$start->toDateString(), $end->toDateString()]);
    }

    /**
     * The five leads that brought in the most cash among the given payments.
     */
    protected function topClients(Collection $cash): array
    {
        $totals = $cash->groupBy('lead_id')->map(fn ($payments) => (float) $payments->sum('amount'))->sortDesc()->take(5);
        $leads = Lead::query()->whereIn('id', $totals->keys())->get(['id', 'reference', 'first_name', 'last_name', 'company_name', 'insurance_type'])->keyBy('id');

        return $totals->map(fn (float $amount, int $leadId) => $leads->has($leadId)
            ? $this->clientRow($leads[$leadId]) + ['received' => round($amount, 2)]
            : null)->filter()->values()->all();
    }

    /**
     * Validated leads still owing money (not paid, not refunded), biggest debt first.
     */
    protected function clientsOwing(Builder $leads): array
    {
        return $leads
            ->where('leads.status', LeadStatusEnum::VALIDE->value)
            ->where('leads.expected_revenue', '>', 0)
            ->where(fn (Builder $query) => $query->whereNull('leads.payment_status')
                ->orWhereNotIn('leads.payment_status', [PaymentStatusEnum::PAYE->value, PaymentStatusEnum::REMBOURSE->value]))
            ->withSum(['payments as received_sum' => fn (Builder $query) => $query->received()], 'amount')
            ->get(['leads.id', 'leads.reference', 'leads.first_name', 'leads.last_name', 'leads.company_name', 'leads.insurance_type', 'leads.expected_revenue', 'leads.payment_status'])
            ->map(fn (Lead $lead) => $this->clientRow($lead) + [
                'payment_status' => $lead->payment_status?->value,
                'payment_status_label' => $lead->payment_status?->label(),
                'expected' => round((float) $lead->expected_revenue, 2),
                'remaining' => round(max(0, (float) $lead->expected_revenue - (float) $lead->received_sum), 2),
            ])
            ->where('remaining', '>', 0)
            ->sortByDesc('remaining')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, reference: ?string, name: string, insurance_type: ?string}
     */
    protected function clientRow(Lead $lead): array
    {
        $person = trim($lead->first_name.' '.$lead->last_name);

        return [
            'id' => $lead->id,
            'reference' => $lead->reference,
            'name' => $lead->company_name ?: ($person ?: ($lead->reference ?? '—')),
            'insurance_type' => $lead->insurance_type,
        ];
    }

    /**
     * Cash in over the period for each of the given agents, best first.
     */
    protected function revenueRanking(array $agentIds, CarbonImmutable $start, CarbonImmutable $end, ?array $sourceIds): array
    {
        $query = Payment::query()->received()
            ->join('leads', 'leads.id', '=', 'payments.lead_id')
            ->whereIn('leads.assigned_to', $agentIds)
            ->whereBetween('payments.payment_date', [$start->toDateString(), $end->toDateString()]);
        LeadSourceScope::leads($query, $sourceIds, 'leads.lead_source_id');

        $totals = $query->groupBy('leads.assigned_to')->selectRaw('leads.assigned_to as agent_id, sum(payments.amount) as total')->pluck('total', 'agent_id');

        return User::query()->whereIn('id', $agentIds)->get(['id', 'name'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'received' => round((float) ($totals[$user->id] ?? 0), 2)])
            ->sortBy([['received', 'desc'], ['name', 'asc']])
            ->values()
            ->all();
    }

    protected function revenueBaseQuery(?Closure $scope, ?string $paymentStatus, ?int $teamId, ?int $agentId, ?string $from, ?string $to): Builder
    {
        $query = Lead::query()->where('status', LeadStatusEnum::VALIDE->value);

        if ($scope) {
            $scope($query);
        }

        if ($paymentStatus) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        if ($agentId) {
            $query->where('assigned_to', $agentId);
        }

        $this->applyDateRange($query, 'validated_at', $from, $to);

        return $query;
    }

    /**
     * Calls with a real counterpart: no internal calls, no CRM click that never became a call.
     */
    protected function realCalls(Builder $query, ?string $from, ?string $to): void
    {
        $query->real();
        $this->applyDateRange($query, 'calls.started_at', $from, $to);
    }

    protected function applyDateRange(Builder $query, string $column, ?string $from, ?string $to): void
    {
        if ($from) {
            $query->where($column, '>=', $from);
        }

        if ($to) {
            $query->where($column, '<=', $to);
        }
    }
}
