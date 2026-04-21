<?php
// app/Services/FraudDetection/RuleEngine.php

namespace App\Services\FraudDetection;

use App\Contracts\Repositories\TransactionRepositoryInterface;
use App\Models\Transaction;

/**
 * Fast, deterministic fraud rules.
 * No AI required — runs synchronously in milliseconds.
 *
 * Open/Closed Principle (O in SOLID):
 * Add new rules by adding new check methods.
 * Never modify existing rule logic.
 */
class RuleEngine
{
    /**
     * Fraud rule identifiers — used as constants to avoid typos
     */
    public const RULE_HIGH_VELOCITY     = 'high_velocity';
    public const RULE_LARGE_AMOUNT      = 'large_amount';
    public const RULE_RAPID_SUCCESSION  = 'rapid_succession';
    public const RULE_OFF_HOURS         = 'off_hours';
    public const RULE_ROUND_AMOUNT      = 'suspicious_round_amount';

    /**
     * Load fraud thresholds from config file.
     * This means you can change thresholds without editing code.
     * Edit config/fraud.php instead.
     */
    private array $config;

// app/Services/FraudDetection/RuleEngine.php

    public function __construct(
        private readonly TransactionRepositoryInterface $transactions
    ) {
        // Use hardcoded defaults if config/fraud.php doesn't exist.
        // config() returns null when the file is missing — (array) cast prevents the TypeError.
        $this->config = config('fraud') ?? [
            'velocity' => [
                'max_per_5_minutes' => 10,
            ],
            'amount' => [
                'large_single_centavos'  => 5000000,  // PHP 50,000
                'round_amount_threshold' => 1000000,  // PHP 10,000
            ],
        ];
    }

    /**
     * Run all rules against a transaction.
     * Returns an array of triggered rules with their risk scores.
     *
     * @param Transaction $transaction
     * @return array Array of ['rule' => string, 'score' => float, 'reason' => string]
     */
    public function analyze(Transaction $transaction): array
    {
        
        $triggered = [];

        // Run each rule. If it returns a result, add it to the list.
        // Using null coalescing to skip rules that didn't trigger.
        $rules = [
            $this->checkHighVelocity($transaction),
            $this->checkLargeAmount($transaction),
            $this->checkRapidSuccession($transaction),
            $this->checkOffHours($transaction),
            $this->checkRoundAmount($transaction),
        ];

        // Filter out nulls (rules that didn't trigger)
        foreach ($rules as $result) {
            if ($result !== null) {
                $triggered[] = $result;
            }
        }

        

        return $triggered;
    }

    /**
     * RULE: High Velocity
     * If a merchant processes more than N transactions in 5 minutes,
     * it's suspicious. Could indicate a compromised merchant account.
     */
    private function checkHighVelocity(Transaction $transaction): ?array
    {
        // Count transactions in the last 5 minutes for this merchant
        $count = $this->transactions->countRecentByMerchant(
            $transaction->merchant_id,
            minutes: 5
        );

        $threshold = $this->config['velocity']['max_per_5_minutes'] ?? 10;

        if ($count > $threshold) {
            return [
                'rule'   => self::RULE_HIGH_VELOCITY,
                'score'  => min(0.9, 0.5 + ($count - $threshold) * 0.05),
                'reason' => "Merchant processed {$count} transactions in 5 minutes (threshold: {$threshold}).",
            ];
        }

        return null; // Rule did not trigger
    }

    /**
     * RULE: Large Amount
     * Single transactions over PHP 50,000 need extra scrutiny.
     */
    private function checkLargeAmount(Transaction $transaction): ?array
    {
        $threshold = $this->config['amount']['large_single_centavos'] ?? 5000000;

        if ($transaction->amount_centavos > $threshold) {
            $amountPhp = $transaction->amount_centavos / 100;
            return [
                'rule'   => self::RULE_LARGE_AMOUNT,
                'score'  => 0.6,
                'reason' => "Single transaction amount PHP {$amountPhp} exceeds threshold.",
            ];
        }

        return null;
    }

    /**
     * RULE: Rapid Succession
     * Multiple transactions within 30 seconds often indicates scripted fraud.
     */
    private function checkRapidSuccession(Transaction $transaction): ?array
    {
        $count = $this->transactions->countRecentByMerchant(
            $transaction->merchant_id,
            minutes: 1 // 1 minute window
        );

        if ($count >= 5) {
            return [
                'rule'   => self::RULE_RAPID_SUCCESSION,
                'score'  => 0.75,
                'reason' => "{$count} transactions within 1 minute. Possible bot activity.",
            ];
        }

        return null;
    }

    /**
     * RULE: Off-Hours Transactions
     * Transactions at 2 AM–5 AM Manila time are unusual for market vendors.
     */
    private function checkOffHours(Transaction $transaction): ?array
    {
        // Convert to Philippine time (UTC+8)
        $hour = now()->setTimezone('Asia/Manila')->hour;

        if ($hour >= 2 && $hour <= 5) {
            return [
                'rule'   => self::RULE_OFF_HOURS,
                'score'  => 0.4,
                'reason' => "Transaction initiated at {$hour}:00 Manila time (unusual hours for MSME).",
            ];
        }

        return null;
    }

    /**
     * RULE: Suspicious Round Amounts
     * Exact round numbers like PHP 10,000 or PHP 50,000 are sometimes
     * used in money laundering to avoid detection thresholds.
     */
    private function checkRoundAmount(Transaction $transaction): ?array
    {
        $threshold = $this->config['amount']['round_amount_threshold'] ?? 1000000; // PHP 10,000

        // Check if divisible by PHP 10,000 (1,000,000 centavos)
        if ($transaction->amount_centavos >= $threshold &&
            $transaction->amount_centavos % 1000000 === 0) {
            return [
                'rule'   => self::RULE_ROUND_AMOUNT,
                'score'  => 0.35,
                'reason' => "Suspicious round amount: PHP " . ($transaction->amount_centavos / 100),
            ];
        }

        return null;
    }
}