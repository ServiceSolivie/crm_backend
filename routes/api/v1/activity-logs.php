<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use Illuminate\Support\Facades\Route;

/*
| « Journal d'activité »: super admin only (checked in the controller).
*/
Route::middleware(['auth:sanctum', 'active'])->prefix('activity-logs')->name('activity-logs.')->group(function () {
    Route::get('/', [ActivityLogController::class, 'index'])->name('index');
    Route::get('/summary', [ActivityLogController::class, 'summary'])->name('summary');
    Route::get('/export', [ActivityLogController::class, 'export'])->name('export');
});
