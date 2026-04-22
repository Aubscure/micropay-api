<?php
// routes/api.php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\MerchantController;
use Illuminate\Support\Facades\Route;

// Public routes 
Route::post('/auth/register', [AuthController::class, 'register'])
     ->name('auth.register')
     ->middleware('throttle:6,1'); 

Route::post('/auth/login', [AuthController::class, 'login'])
     ->name('auth.login')
     ->middleware('throttle:login'); 

// SECURITY FIX: Endpoint to allow cross-domain SPAs to read the CSRF token
Route::get('/auth/csrf', function () {
    return response()->json(['csrf_token' => csrf_token()]);
});

// Protected routes (Sanctum + Custom Limiter) 
Route::middleware(['auth:sanctum', 'throttle:transaction-api'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::apiResource('merchants', MerchantController::class);
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('/transactions/{id}', [TransactionController::class, 'show'])->name('transactions.show');
    Route::post('/transactions/sync', [TransactionController::class, 'syncOffline'])
         ->name('transactions.sync')
         ->middleware('throttle:sync-endpoint');
});