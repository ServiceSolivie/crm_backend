<?php

use App\Http\Controllers\Api\V1\CallController;
use App\Http\Controllers\Api\V1\RingoverWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/calls', [CallController::class, 'index'])->name('calls.index');
    Route::get('/calls/{call}', [CallController::class, 'show'])->name('calls.show');
    Route::get('/leads/{lead}/calls', [CallController::class, 'forLead'])->name('leads.calls.index');
});

// Called by Ringover, not by users: authenticated by its signature instead of a token.
Route::post('/webhooks/ringover', RingoverWebhookController::class)
    ->middleware(['ringover.webhook', 'throttle:600,1'])
    ->name('webhooks.ringover');
