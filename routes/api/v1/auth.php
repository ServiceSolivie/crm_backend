<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {

    Route::post('/register', [AuthController::class, 'register'])
        ->name('register');

    Route::post('/login', [AuthController::class, 'login'])
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
