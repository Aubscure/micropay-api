<?php
// app/Repositories/TransactionRepository.php

namespace App\Repositories;

use App\Interfaces\Repositories\TransactionRepositoryInterface;
use App\Models\Transaction;
use Illuminate\Support\Collection;

/**
 * Concrete implementation of the transaction repository.
 * ALL database queries for transactions go through this class.
 * Controllers and Services never call Transaction::... directly.
 *
 * Single Responsibility Principle (S in SOLID):
 * This class has ONE job — talking to the transactions table.
 */
class TransactionRepository implements TransactionRepositoryInterface
{
    /**
     * Inject the Transaction model.
     * Using constructor injection makes testing easier —
     * you can pass a mock Transaction in unit tests.
     */
    public function __construct(
        private readonly Transaction $model
    ) {}

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Transaction
    {
        // mass-assign all validated fields at once
        return $this->model->create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function findById(string $id): ?Transaction
    {
        // with() eagerly loads related models to avoid N+1 queries.
        // Without this, accessing $transaction->merchant would fire
        // a separate query for every transaction in a list.
        return $this->model
            ->with(['merchant', 'fraudFlags'])
            ->find($id); // Returns null instead of throwing exception
    }

    /**
     * {@inheritdoc}
     */
    public function findByMerchant(string $merchantId, int $limit = 50): Collection
    {
        return $this->model
            ->where('merchant_id', $merchantId)
            ->latest()           // Order by created_at DESC
            ->limit($limit)
            ->get();
    }

    /**
     * {@inheritdoc}
     */
    public function updateStatus(string $id, string $status): bool
    {
        // whereKey() uses the primary key column (id for us)
        // update() returns the number of affected rows
        return (bool) $this->model
            ->whereKey($id)
            ->update(['status' => $status]);
    }

    /**
     * {@inheritdoc}
     */
    public function countRecentByMerchant(string $merchantId, int $minutes): int
    {
        return $this->model
            ->where('merchant_id', $merchantId)
            ->recentWindow($minutes) // Uses the scope we defined in the model
            ->count();
    }

    /**
     * {@inheritdoc}
     */
    public function sumAmountRecentByMerchant(string $merchantId, int $minutes): int
    {
        // sum() returns null if no rows found — (int) cast converts null to 0
        return (int) $this->model
            ->where('merchant_id', $merchantId)
            ->recentWindow($minutes)
            ->sum('amount_centavos');
    }
}