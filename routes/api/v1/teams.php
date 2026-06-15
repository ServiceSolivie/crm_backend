<?php

use App\Http\Controllers\Api\V1\TeamController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('teams')->name('teams.')->group(function () {
    Route::get('/', [TeamController::class, 'index'])->name('index');
    Route::post('/', [TeamController::class, 'store'])->name('store');
    Route::get('/{team}', [TeamController::class, 'show'])->name('show');
    Route::put('/{team}', [TeamController::class, 'update'])->name('update');
    Route::delete('/{team}', [TeamController::class, 'destroy'])->name('destroy');

    Route::get('/{team}/statistics', [TeamController::class, 'statistics'])->name('statistics');

    Route::get('/{team}/members', [TeamController::class, 'members'])->name('members.index');
    Route::post('/{team}/members', [TeamController::class, 'addMember'])->name('members.store');
    Route::delete('/{team}/members/{user}', [TeamController::class, 'removeMember'])->name('members.destroy');
});
