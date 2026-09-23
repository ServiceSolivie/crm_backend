<?php

use App\Http\Controllers\Api\V1\GoogleSheetsWebhookController;
use Illuminate\Support\Facades\Route;

/*
| Called by the Apps Script bound to the Lead/Decennale/Detailles sheets —
| gated by a shared secret (X-Webhook-Secret header) checked against
| GOOGLE_SHEETS_WEBHOOK_SECRET. This is how leads arrive from the sheets;
| the super-admin "Sync from Google Sheets" button is a one-time baseline seed.
*/
Route::prefix('webhooks')->name('webhooks.')->middleware('verify_webhook_secret')->group(function () {
    Route::post('google-sheets/leads', [GoogleSheetsWebhookController::class, 'store'])->name('google-sheets.leads');
});
