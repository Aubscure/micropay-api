<?php
// routes/api.php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\MerchantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| All routes here are automatically prefixed with /api.
| Following the security architecture refactor, these routes now securely 
| support BOTH stateful cookie sessions (for the frontend SPA) and 
| stateless Bearer tokens (for potential third-party integrations).
|
| Route naming: use dot notation (transactions.store)
| for easy URL generation: route('transactions.store')
*/

// Public routes 
// SECURITY: Added a rate limiter to registration to prevent malicious mass-account creation.
Route::post('/auth/register', [AuthController::class, 'register'])
     ->name('auth.register')
     ->middleware('throttle:6,1'); // Limit to 6 attempts per minute per IP

Route::post('/auth/login', [AuthController::class, 'login'])
     ->name('auth.login')
     ->middleware('throttle:login'); // Utilizes the named limiter defined in your provider

// Protected routes (Sanctum + Custom Limiter) 
// The auth:sanctum middleware automatically resolves whether to check for a cookie or a token.
Route::middleware(['auth:sanctum', 'throttle:transaction-api'])->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

    // Merchant management
    Route::apiResource('merchants', MerchantController::class);

    // Transactions
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    
    // Note: 'transaction-api' from AppServiceProvider already covers this at 60/min
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');

    Route::get('/transactions/{id}', [TransactionController::class, 'show'])->name('transactions.show');

    // Batch sync endpoint for offline PWA transactions
    Route::post('/transactions/sync', [TransactionController::class, 'syncOffline'])
         ->name('transactions.sync')
         ->middleware('throttle:sync-endpoint');
});