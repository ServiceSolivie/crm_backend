<?php

use App\Http\Controllers\Api\V1\InsuranceTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('insurance-types')->name('insurance-types.')->group(function () {
    Route::get('/', [InsuranceTypeController::class, 'index'])->name('index');
    Route::post('/', [InsuranceTypeController::class, 'store'])->name('store');
    Route::put('/{insuranceType}', [InsuranceTypeController::class, 'update'])->name('update');
    Route::delete('/{insuranceType}', [InsuranceTypeController::class, 'destroy'])->name('destroy');
});
