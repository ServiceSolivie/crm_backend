<?php

use App\Http\Controllers\Api\V1\DvcTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'active'])->prefix('dvc-types')->name('dvc-types.')->group(function () {
    Route::get('/', [DvcTypeController::class, 'index'])->name('index');
    Route::post('/', [DvcTypeController::class, 'store'])->name('store');
    Route::post('/validate', [DvcTypeController::class, 'validateSchema'])->name('validate');
    Route::post('/preview', [DvcTypeController::class, 'preview'])->name('preview');
    Route::get('/{dvcType}', [DvcTypeController::class, 'show'])->name('show');
    Route::put('/{dvcType}', [DvcTypeController::class, 'update'])->name('update');
    Route::delete('/{dvcType}', [DvcTypeController::class, 'destroy'])->name('destroy');
    Route::post('/{dvcType}/versions', [DvcTypeController::class, 'publish'])->name('versions.store');
    Route::get('/{dvcType}/versions/{version}', [DvcTypeController::class, 'version'])->whereNumber('version')->name('versions.show');
});
