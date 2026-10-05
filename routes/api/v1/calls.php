<?php

use App\Http\Controllers\Api\V1\CallController;
use App\Http\Controllers\Api\V1\RingoverWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/calls', [CallController::class, 'index'])->name('calls.index');
    Route::get('/calls/{call}', [CallController::class, 'show'])->name('calls.show');
    Route::get('/leads/{lead}/calls', [CallController::class, 'forLead'])->name('leads.calls.index');
    Route::post('/leads/{lead}/calls', [CallController::class, 'initiate'])->name('leads.calls.store');
    Route::post('/calls/{call}/ringover-link', [CallController::class, 'linkRingover'])->name('calls.ringover-link');
    Route::patch('/calls/{call}', [CallController::class, 'update'])->name('calls.update');
    Route::post('/calls/{call}/lead', [CallController::class, 'assignLead'])->name('calls.assign-lead');
    Route::get('/calls/{call}/recording', [CallController::class, 'recording'])->name('calls.recording');
    Route::get('/calls/{call}/voicemail', [CallController::class, 'voicemail'])->name('calls.voicemail');
    Route::get('/calls/{call}/transcription', [CallController::class, 'transcription'])->name('calls.transcription');
});

// Called by Ringover, not by users: authenticated by its signature instead of a token.
Route::post('/webhooks/ringover', RingoverWebhookController::class)
    ->middleware(['ringover.webhook', 'throttle:600,1'])
    ->name('webhooks.ringover');
