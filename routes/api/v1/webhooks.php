<?php

use App\Http\Controllers\Api\V1\GoogleSheetsWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Called by the Apps Script bound to the Lead/Decennale/Detailles sheets —
| gated by a shared secret (X-Webhook-Secret header) checked against
| GOOGLE_SHEETS_WEBHOOK_SECRET, since this is the sole ingestion path
| going forward alongside the existing 15-minute google:sync-leads poll.
*/
Route::prefix('webhooks')->name('webhooks.')->middleware('verify_webhook_secret')->group(function () {
    Route::post('google-sheets/leads', [GoogleSheetsWebhookController::class, 'store'])->name('google-sheets.leads');
});
