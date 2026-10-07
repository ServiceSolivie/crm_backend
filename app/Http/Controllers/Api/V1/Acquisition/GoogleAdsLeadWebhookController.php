<?php

namespace App\Http\Controllers\Api\V1\Acquisition;

use App\Http\Controllers\Controller;
use App\Http\Requests\Acquisition\GoogleAdsLeadWebhookRequest;
use App\Services\Acquisition\GoogleAdsLeadIngestionService;
use App\Services\Acquisition\GoogleAdsLegacyForwarder;
use Illuminate\Http\JsonResponse;

class GoogleAdsLeadWebhookController extends Controller
{
    public function __construct(
        protected GoogleAdsLeadIngestionService $ingestion,
        protected GoogleAdsLegacyForwarder $legacyForwarder,
    ) {}

    public function store(GoogleAdsLeadWebhookRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $this->ingestion->assertAuthenticRequest($payload);
        $this->legacyForwarder->forward($request->getContent(), $payload['lead_id']);
        $this->ingestion->ingestVerifiedPayload($payload);

        return response()->json((object) []);
    }
}
