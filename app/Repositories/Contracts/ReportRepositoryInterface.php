<?php

namespace App\Repositories\Contracts;

use App\Filters\AppointmentFilter;
use App\Filters\LeadFilter;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReportRepositoryInterface
{
    /**
     * Per-team performance report: members, leads and appointments totals.
     */
    public function paginateTeamReport(?Closure $scope, ?int $teamId, ?string $from, ?string $to, int $perPage): LengthAwarePaginator;

    /**
     * Per-agent performance report: assigned leads and appointments totals.
     */
    public function paginateAgentReport(?Closure $scope, ?int $teamId, ?string $from, ?string $to, int $perPage): LengthAwarePaginator;

    /**
     * Conversion report: leads grouped by a dimension (source, team, agent
     * or insurance type), with total/validated counts and a conversion rate.
     */
    public function paginateConversionReport(string $groupBy, ?Closure $scope, ?int $teamId, ?string $from, ?string $to, int $perPage): LengthAwarePaginator;

    /**
     * Revenue report: validated leads with revenue data, payments sum, and status.
     */
    public function paginateRevenueReport(?Closure $scope, ?string $paymentStatus, ?int $teamId, ?int $agentId, ?string $from, ?string $to, int $perPage): LengthAwarePaginator;

    /**
     * Revenue summary KPIs for the report header.
     *
     * @return array<string, mixed>
     */
    public function revenueSummary(?Closure $scope, ?string $paymentStatus, ?int $teamId, ?int $agentId, ?string $from, ?string $to): array;

    /**
     * Headline figures for the leads report over the whole filtered set
     * (not just one page): totals by status and by pipeline stage.
     *
     * @return array<string, mixed>
     */
    public function leadSummary(?Closure $scope, LeadFilter $filters): array;

    /**
     * Headline figures for the appointments report over the whole filtered set.
     *
     * @return array<string, mixed>
     */
    public function appointmentSummary(?Closure $scope, AppointmentFilter $filters): array;
}
