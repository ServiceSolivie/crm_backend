<?php

use App\Http\Controllers\Api\V1\RingoverController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('ringover')->name('ringover.')->group(function () {
    Route::get('/status', [RingoverController::class, 'status'])->name('status');

    Route::get('/users', [RingoverController::class, 'users'])->name('users.index');
    Route::post('/users/auto-link', [RingoverController::class, 'autoLink'])->name('users.auto-link');
    Route::put('/users/{user}', [RingoverController::class, 'link'])->name('users.link');
    Route::delete('/users/{user}', [RingoverController::class, 'unlink'])->name('users.unlink');
});
