<?php

namespace App\Repositories\Contracts;

use Closure;

interface DashboardRepositoryInterface
{
    /**
     * Top-level KPI cards: lead and appointment totals, period counts and rates.
     *
     * @return array<string, mixed>
     */
    public function kpis(?Closure $leadScope, ?Closure $appointmentScope, ?string $from, ?string $to): array;

    /**
     * Lead breakdowns by status and insurance type.
     *
     * @return array<string, mixed>
     */
    public function leadStatistics(?Closure $leadScope, ?string $from, ?string $to): array;

    /**
     * Appointment breakdown by status.
     *
     * @return array<string, mixed>
     */
    public function appointmentStatistics(?Closure $appointmentScope, ?string $from, ?string $to): array;

    /**
     * Grouped aggregations (by source, team, agent) depending on the
     * caller's visibility level.
     *
     * @return array<string, mixed>
     */
    public function aggregations(?Closure $leadScope, string $level, ?string $from, ?string $to): array;

    /**
     * Time-series data ready for charting, covering the last $days days.
     *
     * @return array<string, mixed>
     */
    public function charts(?Closure $leadScope, ?Closure $appointmentScope, int $days): array;
}
