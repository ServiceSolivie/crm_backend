<?php

use App\Http\Controllers\Api\V1\LeadController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('leads')->name('leads.')->group(function () {
    Route::get('/', [LeadController::class, 'index'])->name('index');
    Route::post('/', [LeadController::class, 'store'])->name('store');
    Route::get('/{lead}', [LeadController::class, 'show'])->name('show');
    Route::put('/{lead}', [LeadController::class, 'update'])->name('update');
    Route::delete('/{lead}', [LeadController::class, 'destroy'])->name('destroy');

    Route::post('/{lead}/assign', [LeadController::class, 'assign'])->name('assign');
    Route::patch('/{lead}/status', [LeadController::class, 'updateStatus'])->name('update-status');

    Route::get('/{lead}/notes', [LeadController::class, 'notes'])->name('notes.index');
    Route::post('/{lead}/notes', [LeadController::class, 'storeNote'])->name('notes.store');

    Route::get('/{lead}/status-history', [LeadController::class, 'statusHistory'])->name('status-history');
    Route::get('/{lead}/assignment-history', [LeadController::class, 'assignmentHistory'])->name('assignment-history');
});
