<?php

namespace App\Repositories\Eloquent;

use App\Enums\TrustedDeviceStatusEnum;
use App\Models\TrustedDevice;
use App\Repositories\Contracts\TrustedDeviceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class TrustedDeviceRepository extends BaseRepository implements TrustedDeviceRepositoryInterface
{
    public function model(): string
    {
        return TrustedDevice::class;
    }

    public function allForAdmin(): Collection
    {
        return $this->newQuery()
            ->with(['registeredBy:id,name', 'approvedBy:id,name', 'lastUsedBy:id,name'])
            ->orderByRaw('status = ? desc', [TrustedDeviceStatusEnum::PENDING->value])
            ->orderByDesc('last_used_at')
            ->orderByDesc('id')
            ->get();
    }

    public function countsByStatus(): array
    {
        $counts = $this->newQuery()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'pending' => (int) ($counts[TrustedDeviceStatusEnum::PENDING->value] ?? 0),
            'approved' => (int) ($counts[TrustedDeviceStatusEnum::APPROVED->value] ?? 0),
            'revoked' => (int) ($counts[TrustedDeviceStatusEnum::REVOKED->value] ?? 0),
        ];
    }
}
