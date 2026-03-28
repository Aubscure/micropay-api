<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired immediately after a transaction is created.
 *
 * Events are just data carriers — they hold the transaction
 * and pass it to any listeners that are registered for this event.
 * The service that fires this event doesn't know or care what
 * the listeners do with it. That's the decoupling benefit.
 */
class TransactionInitiated
{
    use Dispatchable,   // gives us the static dispatch() helper
        SerializesModels; // safely serializes Eloquent models for the queue

    /**
     * @param Transaction $transaction The newly created transaction
     */
    public function __construct(
        public readonly Transaction $transaction
    ) {}
}