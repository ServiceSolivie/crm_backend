<?php

namespace App\Filters;

use App\Enums\AppointmentStatusEnum;
use App\Enums\LeadStatusEnum;
use Illuminate\Database\Eloquent\Builder;

/**
 * Supported query parameters (flat or nested under filter[...]):
 *
 *   GET /leads?status=VALIDE               (several: status[]=RAPPEL&status[]=OCCUPE or status=RAPPEL,OCCUPE)
 *   GET /leads?stage=follow_up             (every status of a pipeline stage, see LeadStatusEnum::stage())
 *   GET /leads?insurance_type=AUTO         (accepts several values too)
 *   GET /leads?team_id=3
 *   GET /leads?assigned_to=7               (accepts several values too)
 *   GET /leads?unassigned=1                (no agent)
 *   GET /leads?is_doublon=1
 *   GET /leads?dvc_status=SIGNE            (A_GENERER, EN_ATTENTE_SIGNATURE, SIGNE; several values too)
 *   GET /leads?payment_status=PAYE         (NON_PAYE, EN_ATTENTE, PARTIELLEMENT_PAYE, PAYE, REMBOURSE)
 *   GET /leads?due=today                   (open appointment today or overdue; due=overdue for overdue only)
 *   GET /leads?lead_source_id=2 (alias: source_id, accepts several values too)
 *   GET /leads?city=Paris
 *   GET /leads?search=John                 (matches reference, name, phone, email)
 *   GET /leads?from=2026-06-01              (created_at >=)
 *   GET /leads?to=2026-06-30                (created_at <=)
 *   GET /leads?sort=-created_at,reference   or  ?sort_by=created_at&sort_dir=desc
 */
class LeadFilter extends QueryFilter
{
    protected function status(string|array $value): void
    {
        $this->whereIn('status', $value);
    }

    protected function stage(string|array $value): void
    {
        $statuses = [];
        foreach ($this->values($value) as $stage) {
            $statuses = [...$statuses, ...LeadStatusEnum::valuesForStage($stage)];
        }

        // Unknown stage → no match rather than silently ignoring the filter
        $this->builder->whereIn('status', $statuses ?: ['__none__']);
    }

    protected function insuranceType(string|array $value): void
    {
        $this->whereIn('insurance_type', $value);
    }

    protected function teamId(string|array $value): void
    {
        $this->whereIn('team_id', $value);
    }

    protected function assignedTo(string|array $value): void
    {
        $this->whereIn('assigned_to', $value);
    }

    protected function dvcStatus(string|array $value): void
    {
        $this->whereIn('dvc_status', $value);
    }

    protected function paymentStatus(string|array $value): void
    {
        $this->whereIn('payment_status', $value);
    }

    protected function unassigned(string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder->whereNull('assigned_to');
        }
    }

    protected function isDoublon(string $value): void
    {
        $this->builder->where('is_doublon', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * Leads with an open appointment due: "today" = today or already late,
     * "overdue" = scheduled before now and still open.
     */
    protected function due(string $value): void
    {
        $until = match ($value) {
            'today' => now()->endOfDay(),
            'overdue' => now(),
            default => null,
        };

        if (! $until) {
            return;
        }

        $this->builder->whereHas('appointments', fn (Builder $q) => $q
            ->whereIn('status', AppointmentStatusEnum::openValues())
            ->where('scheduled_at', '<=', $until));
    }

    protected function leadSourceId(string|array $value): void
    {
        $this->whereIn('lead_source_id', $value);
    }

    protected function sourceId(string|array $value): void
    {
        $this->whereIn('lead_source_id', $value);
    }

    protected function city(string $value): void
    {
        $this->builder->where('city', 'like', "%{$value}%");
    }

    protected function search(string $value): void
    {
        $this->builder->where(function (Builder $query) use ($value) {
            $query->where('reference', 'like', "%{$value}%")
                ->orWhere('first_name', 'like', "%{$value}%")
                ->orWhere('last_name', 'like', "%{$value}%")
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$value}%"])
                ->orWhere('phone', 'like', "%{$value}%")
                ->orWhere('email', 'like', "%{$value}%");
        });
    }

    protected function from(string $value): void
    {
        $this->builder->where('created_at', '>=', $value);
    }

    protected function to(string $value): void
    {
        $this->builder->where('created_at', '<=', $value);
    }

    /**
     * ?sort_by=next_action_at — leads with the earliest open appointment
     * first; leads with nothing planned go last whatever the direction.
     */
    protected function sortByNextActionAt(string $direction): void
    {
        $next = '(select min(a.scheduled_at) from appointments a where a.lead_id = leads.id and a.deleted_at is null and a.status in (?, ?))';
        $bindings = AppointmentStatusEnum::openValues();

        $this->builder
            ->orderByRaw("{$next} is null", $bindings)
            ->orderByRaw("{$next} ".($direction === 'desc' ? 'desc' : 'asc'), $bindings);
    }

    /**
     * @return array<int, string>
     */
    protected function sortable(): array
    {
        return [
            'created_at',
            'updated_at',
            'reference',
            'status',
            'first_name',
            'last_name',
        ];
    }
}
