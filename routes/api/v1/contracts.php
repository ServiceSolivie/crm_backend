<?php

use App\Http\Controllers\Api\V1\ContractController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('contracts')->name('contracts.')->group(function () {
    Route::get('/', [ContractController::class, 'index'])->name('index');
    Route::get('/templates', [ContractController::class, 'templates'])->name('templates');
    Route::get('/prefill', [ContractController::class, 'prefill'])->name('prefill');
    Route::post('/', [ContractController::class, 'store'])->name('store');
    Route::get('/{contract}/download', [ContractController::class, 'download'])->name('download');
    Route::delete('/{contract}', [ContractController::class, 'destroy'])->name('destroy');
});
