<?php

use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('payments')->group(function () {

    // Initier un paiement
    Route::post('/initiate', [PaymentController::class, 'initiatePayment'])
        ->middleware('auth:sanctum');

    // Vérifier le statut d'un paiement
    Route::post('/check-status', [PaymentController::class, 'checkPaymentStatus'])
        ->middleware('auth:sanctum');

    // Recevoir les callbacks (pas de middleware auth)
    Route::post('/callback', [PaymentController::class, 'handleCallback']);
});
