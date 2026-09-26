<?php

use App\Http\Controllers\Api\V1\FeedbackController;
use Illuminate\Support\Facades\Route;

Route::post('feedback', [FeedbackController::class, 'store'])
    ->middleware(['auth:sanctum', 'active', 'throttle:5,1,feedback'])
    ->name('feedback.store');
