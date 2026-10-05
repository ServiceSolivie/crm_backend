<?php

use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PaymentSessionController;
use App\Http\Controllers\Api\V1\PublicPaymentController;
use Illuminate\Support\Facades\Route;

// Public result page of a payment (the Hyperswitch return_url): no login,
// the 48-character token is the key; throttled per IP.
Route::prefix('public/payments/{token}')->name('public.payments.')->middleware('throttle:30,1')->group(function () {
    Route::post('/return', [PublicPaymentController::class, 'return'])->name('return');
    Route::get('/', [PublicPaymentController::class, 'show'])->name('show');
});

// Payments are read-only (they come from Hyperswitch); the contract total
// can change (e.g. raised to ask an additional payment)
Route::middleware(['auth:sanctum', 'active'])->prefix('leads/{lead}')->name('leads.')->group(function () {
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::patch('/contract-total', [PaymentController::class, 'updateTotal'])->name('contract-total.update');
});

// Payments page: payment links of every lead the user can see
Route::middleware(['auth:sanctum', 'active'])->get('payment-sessions', [PaymentSessionController::class, 'all'])->name('payment-sessions.index');

// Online payment links (Hyperswitch)
Route::middleware(['auth:sanctum', 'active'])->prefix('leads/{lead}/payment-sessions')->name('leads.payment-sessions.')->scopeBindings()->group(function () {
    Route::get('/', [PaymentSessionController::class, 'index'])->name('index');
    Route::post('/', [PaymentSessionController::class, 'store'])->name('store');
    Route::post('/{paymentSession}/cancel', [PaymentSessionController::class, 'cancel'])->name('cancel');
    Route::post('/{paymentSession}/verify', [PaymentSessionController::class, 'verify'])->name('verify');
    // Full refund of a paid request, and the check of a pending refund
    Route::post('/{paymentSession}/refund', [PaymentSessionController::class, 'refund'])->name('refund');
    Route::post('/{paymentSession}/refund/verify', [PaymentSessionController::class, 'verifyRefund'])->name('refund.verify');
});
