<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\InsuranceType\StoreInsuranceTypeRequest;
use App\Http\Requests\InsuranceType\UpdateInsuranceTypeRequest;
use App\Http\Resources\InsuranceTypeResource;
use App\Models\InsuranceType;
use App\Services\InsuranceTypeService;
use Illuminate\Http\JsonResponse;

/**
 * Insurance types, read by everyone (labels, forms, filters) and managed by
 * the super admin.
 */
class InsuranceTypeController extends Controller
{
    public function __construct(protected InsuranceTypeService $service) {}

    public function index(): JsonResponse
    {
        $this->authorize('viewAny', InsuranceType::class);

        return $this->success(InsuranceTypeResource::collection($this->service->listTypes()));
    }

    public function store(StoreInsuranceTypeRequest $request): JsonResponse
    {
        $this->authorize('create', InsuranceType::class);

        return $this->created(new InsuranceTypeResource($this->service->createType($request->validated())), 'Type d\'assurance créé');
    }

    public function update(UpdateInsuranceTypeRequest $request, InsuranceType $insuranceType): JsonResponse
    {
        $this->authorize('update', $insuranceType);

        return $this->success(new InsuranceTypeResource($this->service->updateType($insuranceType, $request->validated())), 'Type d\'assurance mis à jour');
    }

    public function destroy(InsuranceType $insuranceType): JsonResponse
    {
        $this->authorize('delete', $insuranceType);

        $this->service->deleteType($insuranceType);

        return $this->noContent('Type d\'assurance supprimé');
    }
}
