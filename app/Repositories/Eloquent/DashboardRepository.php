<?php

namespace App\Repositories\Eloquent;

use App\Enums\AppointmentStatusEnum;
use App\Enums\InsuranceTypeEnum;
use App\Enums\LeadStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Enums\PaymentStatusEnum;
use App\Models\Appointment;
use App\Models\Lead;
use App\Models\Payment;
use App\Repositories\Contracts\DashboardRepositoryInterface;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function kpis(?Closure $leadScope, ?Closure $appointmentScope, ?string $from, ?string $to): array
    {
        $totalLeads = $this->leadQuery($leadScope, $from, $to)->count();
        $validatedLeads = $this->leadQuery($leadScope, $from, $to)
            ->where('status', LeadStatusEnum::VALIDE->value)
            ->count();

        $newToday = $this->leadQuery($leadScope, null, null)
            ->whereDate('created_at', Carbon::today())
            ->count();

        $newThisWeek = $this->leadQuery($leadScope, null, null)
            ->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])
            ->count();

        $totalAppointments = $this->appointmentQuery($appointmentScope, $from, $to)->count();
        $completedAppointments = $this->appointmentQuery($appointmentScope, $from, $to)
            ->where('status', AppointmentStatusEnum::REALISE->value)
            ->count();

        $appointmentsToday = $this->appointmentQuery($appointmentScope, null, null)
            ->whereDate('scheduled_at', Carbon::today())
            ->count();

        $upcomingAppointments = $this->appointmentQuery($appointmentScope, null, null)
            ->where('status', AppointmentStatusEnum::PLANIFIE->value)
            ->where('scheduled_at', '>=', Carbon::now())
            ->count();

        return [
            'leads' => [
                'total' => $totalLeads,
                'new_today' => $newToday,
                'new_this_week' => $newThisWeek,
                'conversion_rate' => $totalLeads > 0 ? round(($validatedLeads / $totalLeads) * 100, 2) : 0.0,
            ],
            'appointments' => [
                'total' => $totalAppointments,
                'today' => $appointmentsToday,
                'upcoming' => $upcomingAppointments,
                'completion_rate' => $totalAppointments > 0 ? round(($completedAppointments / $totalAppointments) * 100, 2) : 0.0,
            ],
        ];
    }

    public function leadStatistics(?Closure $leadScope, ?string $from, ?string $to): array
    {
        $byStatus = $this->leadQuery($leadScope, $from, $to)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byInsuranceType = $this->leadQuery($leadScope, $from, $to)
            ->selectRaw('insurance_type, count(*) as aggregate')
            ->groupBy('insurance_type')
            ->pluck('aggregate', 'insurance_type');

        return [
            'by_status' => $this->fillCounts(LeadStatusEnum::values(), $byStatus),
            'by_insurance_type' => $this->fillCounts(InsuranceTypeEnum::values(), $byInsuranceType),
        ];
    }

    public function appointmentStatistics(?Closure $appointmentScope, ?string $from, ?string $to): array
    {
        $byStatus = $this->appointmentQuery($appointmentScope, $from, $to)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'by_status' => $this->fillCounts(AppointmentStatusEnum::values(), $byStatus),
        ];
    }

    public function aggregations(?Closure $leadScope, string $level, ?string $from, ?string $to): array
    {
        $leadsBySource = $this->leadQuery($leadScope, $from, $to)
            ->selectRaw('lead_source_id, count(*) as aggregate')
            ->whereNotNull('lead_source_id')
            ->groupBy('lead_source_id')
            ->with('leadSource:id,name')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->lead_source_id,
                'name' => $row->leadSource?->name ?? 'Unknown',
                'count' => (int) $row->aggregate,
            ])
            ->values();

        $result = [
            'leads_by_source' => $leadsBySource,
        ];

        if ($level === 'GLOBAL') {
            $result['leads_by_team'] = $this->leadQuery($leadScope, $from, $to)
                ->selectRaw('team_id, count(*) as aggregate')
                ->whereNotNull('team_id')
                ->groupBy('team_id')
                ->with('team:id,name')
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->team_id,
                    'name' => $row->team?->name ?? 'Unknown',
                    'count' => (int) $row->aggregate,
                ])
                ->values();
        }

        if (in_array($level, ['GLOBAL', 'TEAM'], true)) {
            $result['leads_by_agent'] = $this->leadQuery($leadScope, $from, $to)
                ->selectRaw('assigned_to, count(*) as aggregate, sum(case when status = ? then 1 else 0 end) as validated', [LeadStatusEnum::VALIDE->value])
                ->whereNotNull('assigned_to')
                ->groupBy('assigned_to')
                ->with('assignedAgent:id,name')
                ->orderByDesc('aggregate')
                ->get()
                ->map(fn ($row) => [
                    'id' => $row->assigned_to,
                    'name' => $row->assignedAgent?->name ?? 'Unknown',
                    'count' => (int) $row->aggregate,
                    'validated_count' => (int) $row->validated,
                    'conversion_rate' => $row->aggregate > 0 ? round(($row->validated / $row->aggregate) * 100, 2) : 0.0,
                ])
                ->values();
        }

        return $result;
    }

    public function charts(?Closure $leadScope, ?Closure $appointmentScope, int $days): array
    {
        $start = Carbon::today()->subDays($days - 1);
        $end = Carbon::today();

        $leadsByDay = $this->leadQuery($leadScope, null, null)
            ->selectRaw('DATE(created_at) as date, count(*) as total, sum(case when status = ? then 1 else 0 end) as validated', [LeadStatusEnum::VALIDE->value])
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $appointmentsByDay = $this->appointmentQuery($appointmentScope, null, null)
            ->selectRaw('DATE(scheduled_at) as date, count(*) as total, sum(case when status = ? then 1 else 0 end) as completed', [AppointmentStatusEnum::REALISE->value])
            ->whereBetween('scheduled_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $leadsOverTime = [];
        $appointmentsOverTime = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();

            $leadRow = $leadsByDay->get($key);
            $appointmentRow = $appointmentsByDay->get($key);

            $leadsOverTime[] = [
                'date' => $key,
                'total' => (int) ($leadRow->total ?? 0),
                'validated' => (int) ($leadRow->validated ?? 0),
            ];

            $appointmentsOverTime[] = [
                'date' => $key,
                'total' => (int) ($appointmentRow->total ?? 0),
                'completed' => (int) ($appointmentRow->completed ?? 0),
            ];
        }

        return [
            'leads_over_time' => $leadsOverTime,
            'appointments_over_time' => $appointmentsOverTime,
        ];
    }

    public function revenue(?Closure $leadScope, ?string $from, ?string $to): array
    {
        $validatedQuery = fn () => $this->validatedLeadQuery($leadScope, $from, $to);

        $totalExpected = (clone $validatedQuery())->sum('expected_revenue');
        $totalReceived = Payment::whereIn(
            'lead_id',
            (clone $validatedQuery())->select('id')
        )->sum('amount');
        $totalRemaining = bcsub((string) $totalExpected, (string) $totalReceived, 2);

        $byPaymentStatus = (clone $validatedQuery())
            ->selectRaw('payment_status, count(*) as aggregate')
            ->groupBy('payment_status')
            ->pluck('aggregate', 'payment_status');

        $byPaymentMethod = Payment::whereIn(
            'lead_id',
            (clone $validatedQuery())->select('id')
        )
            ->selectRaw('payment_method, count(*) as aggregate, sum(amount) as total_amount')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->payment_method instanceof PaymentMethodEnum ? $row->payment_method->value : $row->payment_method,
                'label' => $row->payment_method instanceof PaymentMethodEnum ? $row->payment_method->label() : (PaymentMethodEnum::tryFrom($row->payment_method)?->label() ?? $row->payment_method),
                'count' => (int) $row->aggregate,
                'total_amount' => round((float) $row->total_amount, 2),
            ])
            ->values();

        $monthlyTrend = (clone $validatedQuery())
            ->selectRaw("DATE_FORMAT(validated_at, '%Y-%m') as month, count(*) as leads_count, sum(expected_revenue) as expected")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($row) use ($leadScope) {
                $monthStart = Carbon::parse($row->month . '-01')->startOfDay();
                $monthEnd = Carbon::parse($row->month . '-01')->endOfMonth()->endOfDay();

                $received = Payment::whereIn(
                    'lead_id',
                    $this->validatedLeadQuery($leadScope, null, null)->select('id')
                )
                    ->whereBetween('payment_date', [$monthStart, $monthEnd])
                    ->sum('amount');

                return [
                    'month' => $row->month,
                    'leads_count' => (int) $row->leads_count,
                    'expected' => round((float) $row->expected, 2),
                    'received' => round((float) $received, 2),
                ];
            })
            ->values();

        return [
            'kpis' => [
                'total_expected' => round((float) $totalExpected, 2),
                'total_received' => round((float) $totalReceived, 2),
                'total_remaining' => round((float) $totalRemaining, 2),
                'validated_leads' => (clone $validatedQuery())->count(),
                'fully_paid' => (int) ($byPaymentStatus[PaymentStatusEnum::PAYE->value] ?? 0),
                'partially_paid' => (int) ($byPaymentStatus[PaymentStatusEnum::PARTIELLEMENT_PAYE->value] ?? 0),
                'unpaid' => (int) ($byPaymentStatus[PaymentStatusEnum::NON_PAYE->value] ?? 0),
            ],
            'by_payment_status' => $this->fillCounts(PaymentStatusEnum::values(), $byPaymentStatus),
            'by_payment_method' => $byPaymentMethod,
            'monthly_trend' => $monthlyTrend,
        ];
    }

    protected function validatedLeadQuery(?Closure $scope, ?string $from, ?string $to): Builder
    {
        $query = Lead::query()->where('status', LeadStatusEnum::VALIDE->value);

        if ($scope) {
            $scope($query);
        }

        $this->applyDateRange($query, 'validated_at', $from, $to);

        return $query;
    }

    protected function leadQuery(?Closure $scope, ?string $from, ?string $to): Builder
    {
        $query = Lead::query();

        if ($scope) {
            $scope($query);
        }

        $this->applyDateRange($query, 'created_at', $from, $to);

        return $query;
    }

    protected function appointmentQuery(?Closure $scope, ?string $from, ?string $to): Builder
    {
        $query = Appointment::query();

        if ($scope) {
            $scope($query);
        }

        $this->applyDateRange($query, 'scheduled_at', $from, $to);

        return $query;
    }

    protected function applyDateRange(Builder $query, string $column, ?string $from, ?string $to): void
    {
        if ($from) {
            $query->where($column, '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to) {
            $query->where($column, '<=', Carbon::parse($to)->endOfDay());
        }
    }

    /**
     * @param  array<int, string>  $keys
     * @param  Collection<string, int>  $counts
     * @return array<string, int>
     */
    protected function fillCounts(array $keys, Collection $counts): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = (int) ($counts[$key] ?? 0);
        }

        return $result;
    }
}
