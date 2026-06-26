<?php

use App\Http\Controllers\Api\V1\DocumentRequirementController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->group(function () {

    Route::prefix('document-types')->name('document-types.')->group(function () {
        Route::get('/', [DocumentRequirementController::class, 'documentTypes'])->name('index');
        Route::post('/', [DocumentRequirementController::class, 'storeDocumentType'])->name('store');
        Route::put('/{documentType}', [DocumentRequirementController::class, 'updateDocumentType'])->name('update');
        Route::delete('/{documentType}', [DocumentRequirementController::class, 'deleteDocumentType'])->name('destroy');
    });

    Route::prefix('document-requirements')->name('document-requirements.')->group(function () {
        Route::get('/', [DocumentRequirementController::class, 'index'])->name('index');
        Route::post('/sync', [DocumentRequirementController::class, 'syncRequirements'])->name('sync');
    });

});
