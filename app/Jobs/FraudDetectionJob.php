<?php
// app/Jobs/FraudDetectionJob.php

namespace App\Jobs;

use App\Models\FraudFlag;
use App\Models\Transaction;
use App\Services\FraudDetection\AiAnalysisAgent;
use App\Services\FraudDetection\RuleEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Processes fraud detection for a single transaction asynchronously.
 *
 * This runs in a background queue worker — it does NOT block the API response.
 * The vendor gets an immediate response, and fraud checking happens in parallel.
 *
 * Implements ShouldQueue to mark this as a queueable job.
 */
class FraudDetectionJob implements ShouldQueue
{
    use Dispatchable,    // dispatch() static method
        InteractsWithQueue,  // fail(), release() methods
        Queueable,       // Queue configuration
        SerializesModels; // Proper model serialization for the queue

    /**
     * Number of times to retry this job if it fails.
     * Useful for transient failures (e.g., Groq API timeout).
     */
    public int $tries = 3;

    /**
     * Wait this many seconds before retrying.
     * Prevents hammering a temporarily down service.
     */
    public int $backoff = 5;

    /**
     * Maximum runtime before the job is considered stuck.
     */
    public int $timeout = 30;

    /**
     * Pass the transaction model into the job.
     * Laravel's SerializesModels trait handles serializing/deserializing.
     */
    public function __construct(
        public readonly Transaction $transaction
    ) {}

    /**
     * Execute the fraud detection analysis.
     *
     * @param RuleEngine      $ruleEngine   Injected by Laravel's container
     * @param AiAnalysisAgent $aiAgent      Injected by Laravel's container
     */
    public function handle(RuleEngine $ruleEngine, AiAnalysisAgent $aiAgent): void
    {
        Log::info("Starting fraud check for transaction: {$this->transaction->id}");

        // Update status to show fraud check is in progress
        $this->transaction->update(['status' => 'fraud_check']);

        // ── Step 1: Run the fast rule engine ──────────────────────────────
        $flaggedRules = $ruleEngine->analyze($this->transaction);

        // ── Step 2: Decide if AI analysis is needed ──────────────────────
        $needsAiAnalysis = count($flaggedRules) > 0;
        $assessment      = null;

        if ($needsAiAnalysis) {
            // Only call the AI if rules triggered — saves Groq API quota
            $assessment = $aiAgent->analyze($this->transaction, $flaggedRules);
        }

        // ── Step 3: Record all flags in the database ──────────────────────
        foreach ($flaggedRules as $rule) {
            FraudFlag::create([
                'transaction_id' => $this->transaction->id,
                'rule_triggered' => $rule['rule'],
                'risk_score'     => $rule['score'],
                'source'         => 'rule_engine',
                'reason'         => $rule['reason'],
            ]);
        }

        // If AI provided an assessment, record it too
        if ($assessment) {
            FraudFlag::create([
                'transaction_id' => $this->transaction->id,
                'rule_triggered' => 'ai_overall_assessment',
                'risk_score'     => $assessment['risk_score'],
                'source'         => 'ai_agent',
                'reason'         => $assessment['reasoning'],
            ]);
        }

        // ── Step 4: Determine final action ────────────────────────────────
        $finalAction = $this->determineFinalAction($flaggedRules, $assessment);

        // ── Step 5: Update transaction status ─────────────────────────────
        $newStatus = match ($finalAction) {
            'clear'  => 'cleared',  // Safe — will be settled
            'flag'   => 'flagged',  // Needs human review
            'reject' => 'rejected', // Blocked
        };

        $this->transaction->update(['status' => $newStatus]);

        Log::info("Fraud check complete", [
            'transaction_id' => $this->transaction->id,
            'result'         => $newStatus,
            'rules_triggered' => count($flaggedRules),
        ]);

        // If cleared, dispatch settlement job
        if ($newStatus === 'cleared') {
            SettleTransactionJob::dispatch($this->transaction)
                ->delay(now()->addSeconds(2)); // Small delay before settlement
        }
    }

    /**
     * Combine rule engine and AI results to determine the final action.
     */
    private function determineFinalAction(array $flags, ?array $aiAssessment): string
    {
        // No flags and no AI concerns = safe
        if (empty($flags) && $aiAssessment === null) {
            return 'clear';
        }

        // AI assessment takes precedence if available
        if ($aiAssessment !== null) {
            return $aiAssessment['action'];
        }

        // No AI available — use maximum rule score
        $maxScore = max(array_column($flags, 'score'));

        return match (true) {
            $maxScore >= 0.8 => 'reject',
            $maxScore >= 0.5 => 'flag',
            default          => 'clear',
        };
    }
}