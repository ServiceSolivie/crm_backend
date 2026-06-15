<?php

use App\Http\Controllers\Api\V1\LeadSourceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('lead-sources')->name('lead-sources.')->group(function () {
    Route::get('/', [LeadSourceController::class, 'index'])->name('index');
    Route::post('/', [LeadSourceController::class, 'store'])->name('store');
    Route::get('/{leadSource}', [LeadSourceController::class, 'show'])->name('show');
    Route::put('/{leadSource}', [LeadSourceController::class, 'update'])->name('update');
    Route::delete('/{leadSource}', [LeadSourceController::class, 'destroy'])->name('destroy');
});
