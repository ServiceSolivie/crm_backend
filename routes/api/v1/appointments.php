<?php

use App\Http\Controllers\Api\V1\AppointmentController;
use App\Http\Controllers\Api\V1\AppointmentReminderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('appointments')->name('appointments.')->group(function () {
    Route::get('/statistics', [AppointmentController::class, 'statistics'])->name('statistics');

    Route::get('/', [AppointmentController::class, 'index'])->name('index');
    Route::post('/', [AppointmentController::class, 'store'])->name('store');
    Route::get('/{appointment}', [AppointmentController::class, 'show'])->name('show');
    Route::put('/{appointment}', [AppointmentController::class, 'update'])->name('update');
    Route::delete('/{appointment}', [AppointmentController::class, 'destroy'])->name('destroy');

    Route::patch('/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])->name('reschedule');
    Route::patch('/{appointment}/status', [AppointmentController::class, 'updateStatus'])->name('update-status');

    Route::scopeBindings()->group(function () {
        Route::get('/{appointment}/reminders', [AppointmentReminderController::class, 'index'])->name('reminders.index');
        Route::post('/{appointment}/reminders', [AppointmentReminderController::class, 'store'])->name('reminders.store');
        Route::put('/{appointment}/reminders/{reminder}', [AppointmentReminderController::class, 'update'])->name('reminders.update');
        Route::delete('/{appointment}/reminders/{reminder}', [AppointmentReminderController::class, 'destroy'])->name('reminders.destroy');
        Route::patch('/{appointment}/reminders/{reminder}/sent', [AppointmentReminderController::class, 'markSent'])->name('reminders.mark-sent');
    });
});
