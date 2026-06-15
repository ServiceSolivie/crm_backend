<?php

namespace App\Repositories\Eloquent;

use App\Enums\AppointmentStatusEnum;
use App\Filters\AppointmentFilter;
use App\Models\Appointment;
use App\Repositories\Contracts\AppointmentRepositoryInterface;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AppointmentRepository extends BaseRepository implements AppointmentRepositoryInterface
{
    public function model(): string
    {
        return Appointment::class;
    }

    public function paginateFiltered(AppointmentFilter $filters, int $perPage = 15, ?Closure $scope = null): LengthAwarePaginator
    {
        $query = $this->newQuery()->with([
            'lead',
            'agent',
            'creator',
        ]);

        if ($scope) {
            $scope($query);
        }

        return $query->filter($filters)->paginate($perPage);
    }

    public function hasConflict(int $agentId, string $scheduledAt, ?int $excludeId = null): bool
    {
        return $this->model->newQuery()
            ->where('agent_id', $agentId)
            ->where('scheduled_at', $scheduledAt)
            ->where('status', AppointmentStatusEnum::PLANIFIE->value)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->exists();
    }

    public function statistics(?Closure $scope = null, ?string $from = null, ?string $to = null): array
    {
        $query = $this->newQuery();

        if ($scope) {
            $scope($query);
        }

        if ($from) {
            $query->where('scheduled_at', '>=', $from);
        }

        if ($to) {
            $query->where('scheduled_at', '<=', $to);
        }

        $total = $query->count();

        $byStatus = (clone $query)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStatus = collect(AppointmentStatusEnum::values())
            ->mapWithKeys(fn (string $status) => [$status => (int) ($byStatus[$status] ?? 0)])
            ->all();

        $upcoming = (clone $query)
            ->where('status', AppointmentStatusEnum::PLANIFIE->value)
            ->where('scheduled_at', '>=', now())
            ->count();

        $today = (clone $query)
            ->whereDate('scheduled_at', now()->toDateString())
            ->count();

        $completed = $byStatus[AppointmentStatusEnum::REALISE->value];

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'upcoming' => $upcoming,
            'today' => $today,
            'completion_rate' => $total > 0 ? round($completed / $total * 100, 2) : 0.0,
        ];
    }
}
