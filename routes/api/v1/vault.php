<?php

use App\Http\Controllers\Api\V1\Vault\AuditLogController;
use App\Http\Controllers\Api\V1\Vault\CredentialAssignmentController;
use App\Http\Controllers\Api\V1\Vault\CredentialController;
use App\Http\Controllers\Api\V1\Vault\PartnerController;
use App\Http\Controllers\Api\V1\Vault\VaultAgentController;
use App\Http\Controllers\Api\V1\Vault\VaultAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Vault routes — agent-facing (extension) + admin-facing (CRM UI)
|--------------------------------------------------------------------------
*/

Route::prefix('vault')->name('vault.')->group(function () {

    // Extension auth (public, throttled)
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/auth/login', [VaultAuthController::class, 'login'])->name('auth.login');
    });

    // Extension authenticated routes
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/auth/logout', [VaultAuthController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/me', [VaultAuthController::class, 'me'])->name('auth.me');
        Route::get('/partners', [VaultAgentController::class, 'partners'])->name('agent.partners');
        Route::get('/credentials', [VaultAgentController::class, 'credentials'])->name('agent.credentials');
        Route::post('/credentials/{credential}/fill', [VaultAgentController::class, 'fill'])->name('agent.fill');
    });

    // Admin routes (CRM superadmin)
    Route::middleware(['auth:sanctum', 'active'])->prefix('admin')->name('admin.')->group(function () {

        // Partners CRUD
        Route::get('/partners', [PartnerController::class, 'index'])->name('partners.index');
        Route::post('/partners', [PartnerController::class, 'store'])->name('partners.store');
        Route::get('/partners/{partner}', [PartnerController::class, 'show'])->name('partners.show');
        Route::put('/partners/{partner}', [PartnerController::class, 'update'])->name('partners.update');
        Route::delete('/partners/{partner}', [PartnerController::class, 'destroy'])->name('partners.destroy');

        // Credentials CRUD
        Route::get('/credentials', [CredentialController::class, 'index'])->name('credentials.index');
        Route::post('/credentials', [CredentialController::class, 'store'])->name('credentials.store');
        Route::get('/credentials/{credential}', [CredentialController::class, 'show'])->name('credentials.show');
        Route::put('/credentials/{credential}', [CredentialController::class, 'update'])->name('credentials.update');
        Route::delete('/credentials/{credential}', [CredentialController::class, 'destroy'])->name('credentials.destroy');

        // Credential assignment per user
        Route::get('/users/{user}/credentials', [CredentialAssignmentController::class, 'index'])->name('users.credentials.index');
        Route::post('/users/{user}/credentials', [CredentialAssignmentController::class, 'sync'])->name('users.credentials.sync');
        Route::post('/users/{user}/revoke-tokens', [CredentialAssignmentController::class, 'revokeTokens'])->name('users.revoke-tokens');

        // Audit logs
        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });
});
