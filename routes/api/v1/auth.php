<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {

    // No self sign-up: accounts are created by a super admin (Users module).
    // Login attempts are limited in AuthService (4 failed tries per e-mail + IP),
    // plus 20 requests per minute per IP against trying many e-mails.
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:20,1')
        ->name('login');

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
