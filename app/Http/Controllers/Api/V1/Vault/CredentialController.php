<?php

namespace App\Http\Controllers\Api\V1\Vault;

use App\Filters\CredentialFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Credential\StoreCredentialRequest;
use App\Http\Requests\Credential\UpdateCredentialRequest;
use App\Http\Resources\CredentialResource;
use App\Models\Credential;
use App\Services\CredentialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialController extends Controller
{
    public function __construct(protected CredentialService $credentialService) {}

    public function index(Request $request, CredentialFilter $filters): JsonResponse
    {
        $this->authorize('viewAny', Credential::class);

        $perPage = $request->integer('per_page', 15);
        $credentials = $this->credentialService->paginateFiltered($filters, $perPage);

        return $this->success(CredentialResource::collection($credentials));
    }

    public function store(StoreCredentialRequest $request): JsonResponse
    {
        $this->authorize('create', Credential::class);

        $data = $request->validated();
        $mapped = [
            'partner_id' => $data['partner_id'],
            'label' => $data['label'],
            'username_encrypted' => $data['username'] ?? null,
            'email_encrypted' => $data['email'] ?? null,
            'password_encrypted' => $data['password'] ?? null,
        ];

        $credential = $this->credentialService->create($mapped);
        $credential->load('partner:id,name');

        return $this->created(new CredentialResource($credential));
    }

    public function show(Credential $credential): JsonResponse
    {
        $this->authorize('view', $credential);

        $credential->load('partner:id,name');

        return $this->success(new CredentialResource($credential));
    }

    public function update(UpdateCredentialRequest $request, Credential $credential): JsonResponse
    {
        $this->authorize('update', $credential);

        $data = $request->validated();
        $mapped = array_filter([
            'partner_id' => $data['partner_id'] ?? null,
            'label' => $data['label'] ?? null,
            'is_active' => $data['is_active'] ?? null,
        ], fn ($v) => $v !== null);

        if (! empty($data['username'])) {
            $mapped['username_encrypted'] = $data['username'];
        }
        if (! empty($data['email'])) {
            $mapped['email_encrypted'] = $data['email'];
        }
        if (! empty($data['password'])) {
            $mapped['password_encrypted'] = $data['password'];
        }

        $credential = $this->credentialService->update($credential->id, $mapped);
        $credential->load('partner:id,name');

        return $this->success(new CredentialResource($credential));
    }

    public function destroy(Credential $credential): JsonResponse
    {
        $this->authorize('delete', $credential);

        $this->credentialService->delete($credential->id);

        return $this->noContent('Credential deleted successfully');
    }
}
