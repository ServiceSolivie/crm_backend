<?php

namespace App\Http\Controllers\Api\V1\Vault;

use App\Http\Controllers\Controller;
use App\Models\Credential;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialAssignmentController extends Controller
{
    public function index(User $user): JsonResponse
    {
        $this->authorize('assign', Credential::class);

        $assigned = $user->vaultCredentials()
            ->with('partner:id,name')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'label' => $c->label,
                'partner' => ['id' => $c->partner->id, 'name' => $c->partner->name],
            ]);

        return $this->success($assigned);
    }

    public function sync(Request $request, User $user): JsonResponse
    {
        $this->authorize('assign', Credential::class);

        $validated = $request->validate([
            'credential_ids' => ['required', 'array'],
            'credential_ids.*' => ['integer', 'exists:credentials,id'],
        ]);

        $user->vaultCredentials()->sync($validated['credential_ids']);

        return $this->success(null, 'Credentials synced successfully');
    }

    public function revokeTokens(User $user): JsonResponse
    {
        $this->authorize('assign', Credential::class);

        $user->tokens()->delete();

        return $this->success(null, 'Vault sessions revoked successfully');
    }
}
