<?php

namespace App\Providers;

use App\Events\TransactionInitiated;
use App\Listeners\TriggerFraudAnalysis;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Maps events to their listeners.
 * Laravel reads this and auto-wires everything — no manual wiring needed.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event-to-listener map for the application.
     */
    protected $listen = [
        // When TransactionInitiated fires, run TriggerFraudAnalysis
        TransactionInitiated::class => [
            TriggerFraudAnalysis::class,
        ],
    ];
}