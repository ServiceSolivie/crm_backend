<?php

use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->name('read');
});
