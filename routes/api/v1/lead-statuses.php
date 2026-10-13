<?php

use App\Http\Controllers\Api\V1\LeadStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('lead-statuses')->name('lead-statuses.')->group(function () {
    Route::get('/', [LeadStatusController::class, 'index'])->name('index');
    Route::post('/', [LeadStatusController::class, 'store'])->name('store');
    Route::put('/{leadStatus}', [LeadStatusController::class, 'update'])->name('update');
    Route::delete('/{leadStatus}', [LeadStatusController::class, 'destroy'])->name('destroy');
});
