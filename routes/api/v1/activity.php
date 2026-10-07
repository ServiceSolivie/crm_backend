<?php

use App\Http\Controllers\Api\V1\ActivityOperationController;
use Illuminate\Support\Facades\Route;

// Activity journal: operations and their logs (permission audit_logs.view)
Route::middleware(['auth:sanctum', 'active'])->prefix('activity-operations')->name('activity-operations.')->group(function () {
    Route::get('/', [ActivityOperationController::class, 'index'])->name('index');
    Route::get('/summary', [ActivityOperationController::class, 'summary'])->name('summary');
    Route::get('/{activityOperation}', [ActivityOperationController::class, 'show'])->whereNumber('activityOperation')->name('show');
});
