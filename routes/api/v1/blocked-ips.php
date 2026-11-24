<?php

use App\Http\Controllers\Api\V1\BlockedIpController;
use Illuminate\Support\Facades\Route;

/*
| « IP bloquées »: super admin only (checked in the controller).
*/
Route::middleware(['auth:sanctum', 'active'])->prefix('blocked-ips')->name('blocked-ips.')->group(function () {
    Route::get('/', [BlockedIpController::class, 'index'])->name('index');
    Route::post('/', [BlockedIpController::class, 'store'])->name('store');
    Route::delete('/{blockedIp}', [BlockedIpController::class, 'destroy'])->name('destroy');
});
