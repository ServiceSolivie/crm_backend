<?php

use App\Http\Controllers\Api\V1\CampaignController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('campaigns')->name('campaigns.')->group(function () {
    Route::get('/', [CampaignController::class, 'index'])->name('index');
    Route::post('/', [CampaignController::class, 'store'])->name('store');
    Route::get('/{campaign}', [CampaignController::class, 'show'])->name('show');
    Route::put('/{campaign}', [CampaignController::class, 'update'])->name('update');
    Route::delete('/{campaign}', [CampaignController::class, 'destroy'])->name('destroy');
});
