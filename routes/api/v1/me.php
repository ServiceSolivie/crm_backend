<?php

use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('me')->name('me.')->group(function () {
    Route::get('/counters', [MeController::class, 'counters'])->name('counters');
    Route::get('/agenda', [MeController::class, 'agenda'])->name('agenda');
});
