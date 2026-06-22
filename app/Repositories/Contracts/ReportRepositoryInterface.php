<?php

namespace App\Repositories\Contracts;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ReportRepositoryInterface
{
    /**
     * Per-team performance report: members, leads and appointments totals.
     */
    public function paginateTeamReport(?Closure $scope, ?string $from, ?string $to, int $perPage): LengthAwarePaginator;

    /**
     * Per-agent performance report: assigned leads and appointments totals.
     */
    public function paginateAgentReport(?Closure $scope, ?int $teamId, ?string $from, ?string $to, int $perPage): LengthAwarePaginator;

    /**
     * Conversion report: leads grouped by a dimension (source, team, agent
     * or insurance type), with total/validated counts and a conversion rate.
     */
    public function paginateConversionReport(string $groupBy, ?Closure $scope, ?string $from, ?string $to, int $perPage): LengthAwarePaginator;

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
}
