<?php

use App\Http\Controllers\Api\V1\LeadImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('lead-imports')->name('lead-imports.')->group(function () {
    Route::get('/', [LeadImportController::class, 'index'])->name('index');
    Route::post('/', [LeadImportController::class, 'store'])->name('store');
    Route::get('/{leadImport}', [LeadImportController::class, 'show'])->name('show');
});
