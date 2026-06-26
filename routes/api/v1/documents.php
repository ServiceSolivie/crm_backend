<?php

use App\Http\Controllers\Api\V1\LeadDocumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('leads/{lead}/documents')->name('leads.documents.')->group(function () {
    Route::get('/', [LeadDocumentController::class, 'index'])->name('index');
    Route::post('/', [LeadDocumentController::class, 'store'])->name('store');
    Route::patch('/client-type', [LeadDocumentController::class, 'setClientType'])->name('client-type');
    Route::get('/{document}/download', [LeadDocumentController::class, 'download'])->name('download');
    Route::delete('/{document}', [LeadDocumentController::class, 'destroy'])->name('destroy');
});
