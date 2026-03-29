<?php

namespace App\Jobs;

use App\Models\Transaction;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class SettleTransactionJob
{
    use Dispatchable;

    public function __construct(
        public readonly Transaction $transaction
    ) {}

    public function handle(): void
    {
        if ($this->transaction->status !== 'cleared') {
            return;
        }

        $this->transaction->update(['status' => 'settled']);

        Log::info("Transaction settled: {$this->transaction->id}");
    }
}