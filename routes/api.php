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
| All routes here are automatically prefixed with /api
| and served via Sanctum token authentication.
|
| Route naming: use dot notation (transactions.store)
| for easy URL generation: route('transactions.store')
*/

// ── Public routes ──────────────────────────
Route::post('/auth/register', [AuthController::class, 'register'])->name('auth.register');

Route::post('/auth/login', [AuthController::class, 'login'])
     ->name('auth.login')
     ->middleware('throttle:login'); // Better to use a named limiter for login too

// ── Protected routes (Sanctum + Custom Limiter) ───────────────────────────
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
    Route::post('/transactions/sync', [TransactionController::class, 'syncOffline'])->name('transactions.sync');
});
