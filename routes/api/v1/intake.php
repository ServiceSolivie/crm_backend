<?php

use App\Http\Controllers\Api\V1\IntakeController;
use App\Http\Controllers\Api\V1\IntakeRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('intake')->name('intake.')->group(function () {
    /*
    | Given to the marketing team. No login: the intake key (X-Api-Key,
    | Bearer or ?key=) is checked by IntakeService. Any method, any format.
    */
    Route::any('leads', [IntakeController::class, 'receive'])->middleware('throttle:120,1')->name('leads');

<<<<<<< Updated upstream
    // For testing what a sender produces: GET only, no key, recorded the same way
    Route::get('test', [IntakeController::class, 'test'])->middleware('throttle:120,1')->name('test');
=======
    // Testing Facebook's lead webhooks: no key, whatever arrives is recorded
    Route::any('facebook', [IntakeController::class, 'facebook'])->middleware('throttle:120,1')->name('facebook');
>>>>>>> Stashed changes

    // Super admin: the key to give out and the requests received
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('settings', [IntakeRequestController::class, 'settings'])->name('settings');
        Route::post('key', [IntakeRequestController::class, 'regenerateKey'])->name('key');
        Route::get('requests', [IntakeRequestController::class, 'index'])->name('requests.index');
        Route::get('requests/{intakeRequest}', [IntakeRequestController::class, 'show'])->name('requests.show');
        Route::delete('requests/{intakeRequest}', [IntakeRequestController::class, 'destroy'])->name('requests.destroy');
        Route::delete('requests', [IntakeRequestController::class, 'destroyAll'])->name('requests.destroy-all');
    });
});
