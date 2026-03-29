<?php
// app/Services/TransactionService.php

namespace App\Services;

use App\Contracts\Repositories\TransactionRepositoryInterface;
use App\Events\TransactionInitiated;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orchestrates all business logic for transactions.
 *
 * Single Responsibility (S): handles transaction creation flow only.
 * Open/Closed (O): add new behavior via events and listeners, not edits.
 * Dependency Inversion (D): depends on interface, not concrete repository.
 */
class TransactionService
{
    public function __construct(
        // We inject the INTERFACE, not the concrete class.
        // This is the Dependency Inversion Principle in action.
        private readonly TransactionRepositoryInterface $transactions,
    ) {}

    /**
     * Initiate a new payment transaction.
     *
     * This method:
     * 1. Creates the transaction record in the database
     * 2. Fires an event that triggers fraud detection asynchronously
     *
     * @param array $validatedData Data that has already passed FormRequest validation
     * @param string $merchantId   The authenticated merchant's ID
     * @return Transaction
     *
     * @throws \Throwable If the database transaction fails
     */
// app/Services/TransactionService.php
public function initiate(array $validatedData, string $merchantId): Transaction
{
    // DB transaction ONLY wraps the write — nothing else
    $transaction = DB::transaction(function () use ($validatedData, $merchantId) {
        $data = array_merge($validatedData, [
            'merchant_id'  => $merchantId,
            'status'       => 'pending',
            'initiated_at' => now(),
        ]);

        return $this->transactions->create($data);
    });

    // Event fires AFTER commit — DB transaction is fully closed here.
    // With sync queue, FraudDetectionJob runs right here in this same request.
    // The transaction record is guaranteed to exist in the DB at this point.
    event(new TransactionInitiated($transaction));

    Log::info('Transaction initiated', [
        'transaction_id' => $transaction->id,
        'merchant_id'    => $merchantId,
        'amount'         => $transaction->amount_php,
    ]);

    return $transaction;
}

    /**
     * Handle a batch of offline transactions synced from the PWA.
     *
     * When a vendor's phone regains internet after working offline,
     * the PWA sends all queued transactions at once. This method
     * processes each one, skipping duplicates (idempotency).
     *
     * @param array  $transactions Array of transaction data
     * @param string $merchantId
     * @return array  ['created' => int, 'skipped' => int]
     */
    public function syncOfflineBatch(array $transactions, string $merchantId): array
    {
        $created = 0;
        $skipped = 0;

        foreach ($transactions as $txData) {
            // Check if this transaction already exists (by its client-generated UUID).
            // This prevents duplicate records if the sync is sent twice.
            $exists = $this->transactions->findById($txData['id']);

            if ($exists) {
                // Transaction already processed — skip it
                $skipped++;
                continue;
            }

            // Process as a normal transaction but mark it as offline
            $this->initiate(
                array_merge($txData, ['was_offline' => true]),
                $merchantId
            );

            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    public function getMerchantTransactions(string $merchantId, int $perPage = 20)
    {
        // Paginate keeps responses small — critical for low-bandwidth devices
        return Transaction::with('fraudFlags')
            ->where('merchant_id', $merchantId)
            ->latest()
            ->paginate($perPage);
    }
    public function findTransaction(string $id): ?Transaction
    {
        return $this->transactions->findById($id);
    }
}