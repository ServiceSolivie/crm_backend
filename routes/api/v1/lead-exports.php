<?php

use App\Http\Controllers\Api\V1\ImportedLeadsExportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('lead-exports')->name('lead-exports.')->group(function () {
    Route::get('imported/summary', [ImportedLeadsExportController::class, 'summary'])->name('imported.summary');
    Route::get('imported/download', [ImportedLeadsExportController::class, 'download'])->name('imported.download');
});
