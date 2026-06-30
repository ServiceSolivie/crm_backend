<?php

use App\Http\Controllers\Api\V1\TeamLeaderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('team')->name('team.')->group(function () {
    Route::get('/agents', [TeamLeaderController::class, 'agents'])->name('agents');
    Route::get('/follow-ups', [TeamLeaderController::class, 'followUps'])->name('follow-ups');
});
