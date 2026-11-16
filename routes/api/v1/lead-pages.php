<?php

use App\Http\Controllers\Api\V1\LeadPageController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('lead-pages')->name('lead-pages.')->group(function () {
    Route::get('/', [LeadPageController::class, 'index'])->name('index');
    Route::post('/', [LeadPageController::class, 'store'])->name('store');
    Route::put('/{leadPage}', [LeadPageController::class, 'update'])->name('update');
    Route::delete('/{leadPage}', [LeadPageController::class, 'destroy'])->name('destroy');
});
