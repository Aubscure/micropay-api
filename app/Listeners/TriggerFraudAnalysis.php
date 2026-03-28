<?php

namespace App\Listeners;

use App\Events\TransactionInitiated;
use App\Jobs\FraudDetectionJob;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listens for TransactionInitiated and dispatches the fraud detection job.
 *
 * Implementing ShouldQueue means this listener itself runs asynchronously —
 * the HTTP response is returned to the user before this even starts.
 */
class TriggerFraudAnalysis implements ShouldQueue
{
    /**
     * Handle the event.
     * Dispatches the fraud detection job to the queue.
     */
    public function handle(TransactionInitiated $event): void
    {
        // Push the fraud detection job onto the 'fraud' queue.
        // This runs in the background — the vendor's payment screen
        // already showed "success" by the time this executes.
        FraudDetectionJob::dispatch($event->transaction)
            ->onQueue('fraud');
    }
}