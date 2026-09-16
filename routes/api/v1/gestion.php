<?php

use App\Http\Controllers\Api\V1\GestionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('gestion')->name('gestion.')->group(function () {
    Route::get('/dashboard', [GestionController::class, 'dashboard'])->name('dashboard');
});

Route::middleware(['auth:sanctum', 'active'])->prefix('leads/{lead}/gestion')->name('leads.gestion.')->group(function () {
    Route::post('/flag-issue', [GestionController::class, 'flagIssue'])->name('flag-issue');
});
