<?php

namespace App\Listeners;

use App\Events\TransactionInitiated;
use App\Jobs\FraudDetectionJob;

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
        // With sync driver this runs immediately in the same request
        FraudDetectionJob::dispatch($event->transaction);
    }
}