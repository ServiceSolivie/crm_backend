<?php

namespace App\Http\Controllers\Api\V1\Acquisition;

use App\Http\Controllers\Controller;
use App\Http\Requests\Acquisition\GoogleAdsLeadWebhookRequest;
use App\Services\Acquisition\GoogleAdsLeadIngestionService;
use Illuminate\Http\JsonResponse;

class GoogleAdsLeadWebhookController extends Controller
{
    public function __construct(protected GoogleAdsLeadIngestionService $ingestion) {}

    public function store(GoogleAdsLeadWebhookRequest $request): JsonResponse
    {
        $this->ingestion->ingest($request->validated());

        return response()->json((object) []);
    }
}
