<?php

namespace App\Listeners;

use App\Events\TransactionInitiated;
use App\Jobs\FraudDetectionJob;

// No ShouldQueue — with sync driver, this runs inline immediately
class TriggerFraudAnalysis
{
    public function handle(TransactionInitiated $event): void
    {
        // Dispatches and RUNS the job synchronously right here
        FraudDetectionJob::dispatchSync($event->transaction);
    }
}