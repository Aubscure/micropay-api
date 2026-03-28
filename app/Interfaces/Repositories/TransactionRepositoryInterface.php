<?php
// app/Interfaces/Repositories/TransactionRepositoryInterface.php

namespace App\Interfaces\Repositories;

use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Dependency Inversion Principle (D in SOLID):
 * High-level modules (Services) depend on this abstraction,
 * not on the concrete Eloquent implementation.
 *
 * This means you can swap out the database layer (e.g., switch from
 * PostgreSQL to MongoDB) without touching any service code.
 */
interface TransactionRepositoryInterface
{
    /**
     * Create a new transaction record.
     *
     * @param array $data Validated transaction data
     * @return Transaction The created model
     */
    public function create(array $data): Transaction;

    /**
     * Find a transaction by its UUID.
     *
     * @param string $id UUID of the transaction
     * @return Transaction|null Returns null if not found
     */
    public function findById(string $id): ?Transaction;

    /**
     * Get all transactions for a specific merchant.
     *
     * @param string $merchantId UUID of the merchant
     * @param int    $limit      Max number of results
     * @return Collection
     */
    public function findByMerchant(string $merchantId, int $limit = 50): Collection;

    /**
     * Update the status of a transaction.
     *
     * @param string $id     UUID of the transaction
     * @param string $status New status value
     * @return bool True if update succeeded
     */
    public function updateStatus(string $id, string $status): bool;

    /**
     * Count transactions by a merchant in the last N minutes.
     * Used by the fraud rule engine for velocity checks.
     *
     * @param string $merchantId UUID
     * @param int    $minutes    Time window
     * @return int   Number of transactions
     */
    public function countRecentByMerchant(string $merchantId, int $minutes): int;

    /**
     * Get the total amount transacted by a merchant in the last N minutes.
     * Used for transaction volume fraud checks.
     *
     * @param string $merchantId UUID
     * @param int    $minutes    Time window
     * @return int   Total in centavos
     */
    public function sumAmountRecentByMerchant(string $merchantId, int $minutes): int;
}