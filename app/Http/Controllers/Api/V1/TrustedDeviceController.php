<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeviceRestrictionEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\TrustedDeviceResource;
use App\Models\TrustedDevice;
use App\Services\TrustedDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The "Appareils" page: machines allowed to open the CRM and the switch
 * that turns the restriction on.
 */
class TrustedDeviceController extends Controller
{
    public function __construct(protected TrustedDeviceService $deviceService) {}

    public function index(): JsonResponse
    {
        $this->authorize('manage', TrustedDevice::class);

        return $this->success(TrustedDeviceResource::collection($this->deviceService->list()));
    }

    public function settings(): JsonResponse
    {
        $this->authorize('manage', TrustedDevice::class);

        return $this->success($this->deviceService->settings());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $this->authorize('manage', TrustedDevice::class);

        $data = $request->validate(['mode' => ['required', Rule::in(DeviceRestrictionEnum::values())]]);

        return $this->success($this->deviceService->setMode(DeviceRestrictionEnum::from($data['mode'])), 'Restriction mise à jour');
    }

    public function update(Request $request, TrustedDevice $device): JsonResponse
    {
        $this->authorize('manage', TrustedDevice::class);

        $data = $request->validate(['label' => ['present', 'nullable', 'string', 'max:80']]);

        return $this->success(new TrustedDeviceResource($this->deviceService->rename($device, $data['label'])), 'Poste renommé');
    }

    public function approve(Request $request, TrustedDevice $device): JsonResponse
    {
        $this->authorize('manage', TrustedDevice::class);

        return $this->success(new TrustedDeviceResource($this->deviceService->approve($device, $request->user())), 'Poste approuvé');
    }

    public function revoke(TrustedDevice $device): JsonResponse
    {
        $this->authorize('manage', TrustedDevice::class);

        return $this->success(new TrustedDeviceResource($this->deviceService->revoke($device)), 'Poste révoqué');
    }

    public function destroy(TrustedDevice $device): JsonResponse
    {
        $this->authorize('manage', TrustedDevice::class);

        $this->deviceService->delete($device);

        return $this->noContent('Poste supprimé');
    }
}
