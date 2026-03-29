<?php
// app/Console/Commands/RecoverStuckTransactions.php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Events\TransactionInitiated;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Finds transactions stuck in 'pending' or 'fraud_check' longer than
 * 10 minutes and re-fires the fraud detection event.
 *
 * This replaces a Dead Letter Queue for the free tier.
 * Run via scheduler every 10 minutes.
 */
class RecoverStuckTransactions extends Command
{
    protected $signature   = 'micropay:recover-stuck';
    protected $description = 'Re-process transactions stuck in pending/fraud_check state';

    public function handle(): void
    {
        // Find transactions that have been stuck for more than 10 minutes.
        // Anything still 'pending' after 10 min means the fraud job failed silently.
        $stuck = Transaction::whereIn('status', ['pending', 'fraud_check'])
            ->where('created_at', '<', now()->subMinutes(10))
            ->limit(10) // Process max 10 at a time to avoid timeouts
            ->get();

        if ($stuck->isEmpty()) {
            $this->info('No stuck transactions found.');
            return;
        }

        $this->info("Found {$stuck->count()} stuck transactions. Re-processing...");

        foreach ($stuck as $transaction) {
            try {
                // Reset to pending so the fraud job processes it fresh
                $transaction->update(['status' => 'pending']);

                // Re-fire the event — fraud detection runs again
                event(new TransactionInitiated($transaction));

                Log::info("Recovered stuck transaction: {$transaction->id}");
                $this->line("  Recovered: {$transaction->id}");

            } catch (\Exception $e) {
                // Log but continue — don't let one failure block others
                Log::error("Failed to recover transaction: {$transaction->id}", [
                    'error' => $e->getMessage(),
                ]);
                $this->error("  Failed: {$transaction->id} — {$e->getMessage()}");
            }
        }

        $this->info('Recovery complete.');
    }
}