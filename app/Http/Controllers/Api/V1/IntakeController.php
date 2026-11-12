<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\IntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The address given to the marketing team: /api/v1/intake/leads.
 * Test phase: whatever arrives is recorded as is, nothing else happens.
 * Any format and any method are accepted; only the key decides.
 *
<<<<<<< Updated upstream
 * /api/v1/intake/test is the same for GET requests, without a key.
=======
 * /api/v1/intake/facebook is the same without a key, to test Facebook's
 * lead webhooks.
>>>>>>> Stashed changes
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

<<<<<<< Updated upstream
    public function test(Request $request): JsonResponse
    {
        $recorded = $this->intake->record($request, keyRequired: false);

=======
    public function facebook(Request $request): JsonResponse|Response
    {
        $recorded = $this->intake->record($request, keyRequired: false);

        // Facebook checks the address first: it expects hub.challenge back, as is
        if ($request->isMethod('GET') && $request->query('hub_mode') === 'subscribe') {
            return response((string) $request->query('hub_challenge'))->header('Content-Type', 'text/plain');
        }

>>>>>>> Stashed changes
        return response()->json(['received' => true, 'id' => $recorded->id]);
    }
}
