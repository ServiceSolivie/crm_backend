<?php

namespace App\Services;

use App\Enums\DeviceRestrictionEnum;
use App\Enums\TrustedDeviceStatusEnum;
use App\Exceptions\ApiException;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Repositories\Contracts\TrustedDeviceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the super admin does on the "Appareils" page. The login side (the
 * passkey checks) is DeviceTrustService.
 */
class TrustedDeviceService
{
    public function __construct(
        protected TrustedDeviceRepositoryInterface $devices,
        protected DeviceTrustService $trust,
    ) {}

    public function list(): Collection
    {
        return $this->devices->allForAdmin();
    }

    /**
     * @return array{mode: string, counts: array{pending: int, approved: int, revoked: int}}
     */
    public function settings(): array
    {
        return [
            'mode' => $this->trust->mode()->value,
            'counts' => $this->devices->countsByStatus(),
        ];
    }

    /**
     * @return array{mode: string, counts: array{pending: int, approved: int, revoked: int}, closed_sessions: int}
     */
    public function setMode(DeviceRestrictionEnum $mode): array
    {
        $closed = $this->trust->setMode($mode);

        return [...$this->settings(), 'closed_sessions' => $closed];
    }

    public function rename(TrustedDevice $device, ?string $label): TrustedDevice
    {
        $device->update(['label' => $label !== null && trim($label) !== '' ? trim($label) : null]);

        return $this->fresh($device);
    }

    /**
     * A waiting or a revoked machine becomes usable.
     */
    public function approve(TrustedDevice $device, User $admin): TrustedDevice
    {
        if ($device->isApproved()) {
            return $this->fresh($device);
        }

        $device->update([
            'status' => TrustedDeviceStatusEnum::APPROVED->value,
            'approved_by' => $admin->id,
            'approved_at' => now(),
            'revoked_at' => null,
            'revoked_reason' => null,
        ]);

        return $this->fresh($device);
    }

    /**
     * The machine can no longer log in and its open sessions are closed now.
     */
    public function revoke(TrustedDevice $device): TrustedDevice
    {
        if (! $device->isApproved()) {
            throw new ApiException('Seul un poste approuvé peut être révoqué.', 409);
        }

        $this->trust->revoke($device, 'admin');

        return $this->fresh($device);
    }

    /**
     * Refuse a request, or remove a revoked machine for good. The passkey
     * left on the PC is then unknown to the CRM.
     */
    public function delete(TrustedDevice $device): bool
    {
        if ($device->isApproved()) {
            throw new ApiException('Révoquez ce poste avant de le supprimer.', 409);
        }

        $this->trust->closeSessionsOf($device);

        return (bool) $device->delete();
    }

    protected function fresh(TrustedDevice $device): TrustedDevice
    {
        return $device->refresh()->load(['registeredBy:id,name', 'approvedBy:id,name', 'lastUsedBy:id,name']);
    }
}
