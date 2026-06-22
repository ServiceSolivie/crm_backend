<?php

use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('reports')->name('reports.')->group(function () {
    Route::get('/leads', [ReportController::class, 'leads'])->name('leads');
    Route::get('/appointments', [ReportController::class, 'appointments'])->name('appointments');
    Route::get('/teams', [ReportController::class, 'teams'])->name('teams');
    Route::get('/agents', [ReportController::class, 'agents'])->name('agents');
    Route::get('/conversion', [ReportController::class, 'conversion'])->name('conversion');
    Route::get('/revenue', [ReportController::class, 'revenue'])->name('revenue');
    Route::get('/revenue/summary', [ReportController::class, 'revenueSummary'])->name('revenue.summary');
});
