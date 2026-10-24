<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\IntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The address given to the marketing team: /api/v1/intake/leads.
 * Test phase: whatever arrives is recorded as is, nothing else happens.
 * Any format and any method are accepted; only the key decides.
 */
class IntakeController extends Controller
{
    public function __construct(protected IntakeService $intake) {}

    public function receive(Request $request): JsonResponse
    {
        $recorded = $this->intake->record($request);

        if (! $recorded->key_valid) {
            return response()->json(['received' => false, 'message' => 'Invalid or missing API key.'], 401);
        }

        return response()->json(['received' => true, 'id' => $recorded->id]);
    }
}
