<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (v1)
|--------------------------------------------------------------------------
|
| All API routes are versioned under /api/v1. Business module routes
| (leads, teams, appointments, etc.) are registered inside this group
| as they are built.
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    require __DIR__.'/api/v1/auth.php';
    require __DIR__.'/api/v1/leads.php';
    require __DIR__.'/api/v1/appointments.php';
    require __DIR__.'/api/v1/teams.php';
    require __DIR__.'/api/v1/dashboard.php';
    require __DIR__.'/api/v1/reports.php';
    require __DIR__.'/api/v1/users.php';
    require __DIR__.'/api/v1/lead-sources.php';
    require __DIR__.'/api/v1/lead-imports.php';
    require __DIR__.'/api/v1/payments.php';

    // Further module route files are required here as they are built.
});
