<?php

namespace App\Http\Controllers\Api\V1\Vault;

use App\Filters\PartnerFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Partner\StorePartnerRequest;
use App\Http\Requests\Partner\UpdatePartnerRequest;
use App\Http\Resources\PartnerResource;
use App\Models\Partner;
use App\Services\PartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function __construct(protected PartnerService $partnerService) {}

    public function index(Request $request, PartnerFilter $filters): JsonResponse
    {
        $this->authorize('viewAny', Partner::class);

        $perPage = $request->integer('per_page', 15);
        $partners = $this->partnerService->paginateFiltered($filters, $perPage);

        return $this->success(PartnerResource::collection($partners));
    }

    public function store(StorePartnerRequest $request): JsonResponse
    {
        $this->authorize('create', Partner::class);

        $data = $request->validated();
        $data['field_mapping'] = $this->buildFieldMapping($data);
        unset($data['form_selector'], $data['identity_selector'], $data['password_selector']);

        $partner = $this->partnerService->create($data);

        return $this->created(new PartnerResource($partner));
    }

    public function show(Partner $partner): JsonResponse
    {
        $this->authorize('view', $partner);

        return $this->success(new PartnerResource($partner));
    }

    public function update(UpdatePartnerRequest $request, Partner $partner): JsonResponse
    {
        $this->authorize('update', $partner);

        $data = $request->validated();
        $data['field_mapping'] = $this->buildFieldMapping($data);
        unset($data['form_selector'], $data['identity_selector'], $data['password_selector']);

        $partner = $this->partnerService->update($partner->id, $data);

        return $this->success(new PartnerResource($partner));
    }

    public function destroy(Partner $partner): JsonResponse
    {
        $this->authorize('delete', $partner);

        $this->partnerService->delete($partner->id);

        return $this->noContent('Partner deleted successfully');
    }

    private function buildFieldMapping(array $data): ?array
    {
        $form = $data['form_selector'] ?? null;
        $identity = $data['identity_selector'] ?? null;
        $password = $data['password_selector'] ?? null;

        if ($form || $identity || $password) {
            return [
                'form_selector' => $form,
                'identity_selector' => $identity,
                'password_selector' => $password,
            ];
        }

        return null;
    }
}
