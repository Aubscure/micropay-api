<?php
// app/Http/Controllers/Api/TransactionController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InitiateTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Handles HTTP layer for transaction operations.
 *
 * Controllers should be THIN. No business logic here.
 * The controller only:
 * 1. Receives the HTTP request
 * 2. Delegates to a Service
 * 3. Returns an HTTP response
 */
class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService
    ) {}

    /**
     * GET /api/transactions
     *
     * List the authenticated merchant's transactions.
     * Paginated to keep payloads small (important for low bandwidth).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $transactions = $this->transactionService
            ->getMerchantTransactions(
                merchantId: $request->user()->merchant->id,
                perPage: 20 // Small pages = faster load on 3G
            );

        // TransactionResource controls exactly what JSON fields are returned.
        // This prevents accidentally leaking sensitive internal fields.
        return TransactionResource::collection($transactions);
    }

    /**
     * POST /api/transactions
     *
     * Initiate a new payment transaction.
     * Uses InitiateTransactionRequest for automatic validation.
     * If validation fails, Laravel automatically returns a 422 response.
     */
    public function store(InitiateTransactionRequest $request): JsonResponse
    {
        $transaction = $this->transactionService->initiate(
            validatedData: $request->validated(), // Only safe, validated data
            merchantId: $request->user()->merchant->id
        );

        return response()->json([
            'data'    => new TransactionResource($transaction),
            'message' => 'Transaction initiated. Fraud check in progress.',
        ], 201); // 201 Created
    }

    /**
     * POST /api/transactions/sync
     *
     * Receive a batch of offline-queued transactions from the PWA.
     * Called when the device regains internet connectivity.
     */
    public function syncOffline(Request $request): JsonResponse
    {
        $request->validate([
            'transactions'         => 'required|array|max:100', // Max 100 at once
            'transactions.*.id'    => 'required|uuid',
            'transactions.*.amount_centavos' => 'required|integer|min:1',
            'transactions.*.currency'        => 'required|string|size:3',
        ]);

        $result = $this->transactionService->syncOfflineBatch(
            transactions: $request->input('transactions'),
            merchantId: $request->user()->merchant->id
        );

        return response()->json([
            'message' => "Sync complete: {$result['created']} created, {$result['skipped']} skipped.",
            'data'    => $result,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $transaction = $this->transactionService->findTransaction($id);

        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found.'], 404);
        }

        // Ensure the requesting user owns the merchant who received this transaction
        if ($transaction->merchant->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(['data' => new TransactionResource($transaction)]);
    }
}