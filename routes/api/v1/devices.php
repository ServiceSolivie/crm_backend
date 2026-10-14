<?php

use App\Http\Controllers\Api\V1\TrustedDeviceController;
use Illuminate\Support\Facades\Route;

// Approved machines, super admin side. The login side is in auth.php.
Route::middleware(['auth:sanctum', 'active'])->prefix('devices')->name('devices.')->group(function () {
    Route::get('/', [TrustedDeviceController::class, 'index'])->name('index');
    Route::get('/settings', [TrustedDeviceController::class, 'settings'])->name('settings');
    Route::put('/settings', [TrustedDeviceController::class, 'updateSettings'])->name('settings.update');
    Route::put('/{device}', [TrustedDeviceController::class, 'update'])->name('update');
    Route::post('/{device}/approve', [TrustedDeviceController::class, 'approve'])->name('approve');
    Route::post('/{device}/revoke', [TrustedDeviceController::class, 'revoke'])->name('revoke');
    Route::delete('/{device}', [TrustedDeviceController::class, 'destroy'])->name('destroy');
});
