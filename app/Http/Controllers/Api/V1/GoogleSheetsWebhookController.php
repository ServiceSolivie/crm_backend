<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\GoogleSheetLeadImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleSheetsWebhookController extends Controller
{
    public function __construct(protected GoogleSheetLeadImporter $importer) {}

    /**
     * Pushed by the Apps Script bound to the Lead/Decennale/Detailles
     * sheets, gated by the verify_webhook_secret middleware. No strict
     * validation beyond that — the sheets' field names/shape keep changing
     * between calls, so this is deliberately permissive: known fields (see
     * GoogleSheetLeadImporter::WEBHOOK_FIELD_ALIASES) map to real Lead
     * columns, everything else is preserved as JSON in Lead::comment.
     */
    public function store(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (! is_array($payload) || empty($payload)) {
            return $this->success(['status' => 'skipped'], 'Empty or non-JSON payload');
        }

        $result = $this->importer->importFromWebhookPayload($payload);

        return $this->success($result, "Lead {$result['status']}");
    }
}
