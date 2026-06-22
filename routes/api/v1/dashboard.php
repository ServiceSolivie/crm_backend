<?php

use App\Http\Controllers\Api\V1\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('dashboard')->name('dashboard.')->group(function () {
    Route::get('/kpis', [DashboardController::class, 'kpis'])->name('kpis');
    Route::get('/statistics', [DashboardController::class, 'statistics'])->name('statistics');
    Route::get('/aggregations', [DashboardController::class, 'aggregations'])->name('aggregations');
    Route::get('/charts', [DashboardController::class, 'charts'])->name('charts');
    Route::get('/revenue', [DashboardController::class, 'revenue'])->name('revenue');
});
