<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceLoginController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {

    // No self sign-up: accounts are created by a super admin (Users module).
    // Login attempts are limited in AuthService (4 failed tries per e-mail + IP),
    // plus 20 requests per minute per IP against trying many e-mails.
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:20,1')
        ->name('login');

    // Approved machines: second step of the login, reached with the ticket
    // given after the password (no session yet).
    Route::prefix('login/device')->name('login.device.')->middleware('throttle:20,1')->group(function () {
        Route::post('/verify', [DeviceLoginController::class, 'verify'])->name('verify');
        Route::post('/register-options', [DeviceLoginController::class, 'registerOptions'])->name('register-options');
        Route::post('/register', [DeviceLoginController::class, 'register'])->name('register');
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {

        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('logout');

        Route::get('/me', [AuthController::class, 'me'])
            ->name('me');

        // Ajouter ces routes
        Route::put('/profile', [AuthController::class, 'updateProfile'])
            ->name('profile.update');

        Route::put('/password', [AuthController::class, 'changePassword'])
            ->name('password.update');
    });
});
