<?php

namespace App\Http\Controllers\Api\V1\Vault;

use App\Http\Controllers\Controller;
use App\Models\Credential;
use App\Models\Partner;
use App\Models\VaultAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VaultAgentController extends Controller
{
    public function partners(Request $request): JsonResponse
    {
        $partners = Partner::where('is_active', true)
            ->whereHas('credentials', fn ($q) => $q->where('is_active', true))
            ->get(['id', 'name', 'login_url', 'domain', 'field_mapping']);

        return response()->json($partners);
    }

    public function credentials(Request $request): JsonResponse
    {
        $credentials = Credential::where('is_active', true)
            ->get(['id', 'label', 'partner_id']);

        return response()->json($credentials);
    }

    public function fill(Request $request, Credential $credential): JsonResponse
    {
        if (! $credential->is_active || ! $credential->partner?->is_active) {
            return response()->json(['message' => 'Credential or partner is inactive.'], 403);
        }

        VaultAuditLog::create([
            'user_id' => $request->user()->id,
            'credential_id' => $credential->id,
            'action' => 'fill',
            'domain' => $request->input('domain'),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);

        return response()->json([
            'username' => $credential->username_encrypted,
            'email' => $credential->email_encrypted,
            'password' => $credential->password_encrypted,
            'extra_fields' => $credential->extra_fields_encrypted,
        ]);
    }
}
