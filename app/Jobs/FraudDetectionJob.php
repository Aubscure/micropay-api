<?php

namespace App\Jobs;

use App\Models\FraudFlag;
use App\Models\Transaction;
use App\Services\FraudDetection\AiAnalysisAgent;
use App\Services\FraudDetection\RuleEngine;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class FraudDetectionJob
{
    use Dispatchable;

    public function __construct(
        public readonly Transaction $transaction
    ) {}

    public function handle(RuleEngine $ruleEngine, AiAnalysisAgent $aiAgent): void
    {

        // Guard: if already processed, skip — prevents double-run
        if ($this->transaction->status !== 'pending') {
            Log::info("Skipping fraud check — already processed: {$this->transaction->id}");
            return;
        }
        Log::info("Starting fraud check: {$this->transaction->id}");

        $this->transaction->update(['status' => 'fraud_check']);

        $flaggedRules = $ruleEngine->analyze($this->transaction);
        $assessment   = null;

        if (count($flaggedRules) > 0) {
            $assessment = $aiAgent->analyze($this->transaction, $flaggedRules);
        }

        foreach ($flaggedRules as $rule) {
            FraudFlag::create([
                'transaction_id' => $this->transaction->id,
                'rule_triggered' => $rule['rule'],
                'risk_score'     => $rule['score'],
                'source'         => 'rule_engine',
                'reason'         => $rule['reason'],
            ]);
        }

        if ($assessment) {
            FraudFlag::create([
                'transaction_id' => $this->transaction->id,
                'rule_triggered' => 'ai_overall_assessment',
                'risk_score'     => $assessment['risk_score'],
                'source'         => 'ai_agent',
                'reason'         => $assessment['reasoning'],
            ]);
        }

        $finalAction = $this->determineFinalAction($flaggedRules, $assessment);

        $newStatus = match ($finalAction) {
            'clear'  => 'cleared',
            'flag'   => 'flagged',
            'reject' => 'rejected',
        };

        $this->transaction->update(['status' => $newStatus]);

        Log::info("Fraud check complete: {$this->transaction->id} → {$newStatus}");

        if ($newStatus === 'cleared') {
            SettleTransactionJob::dispatchSync($this->transaction);
        }
    }

    private function determineFinalAction(array $flags, ?array $aiAssessment): string
    {
        if (empty($flags) && $aiAssessment === null) {
            return 'clear';
        }

        if ($aiAssessment !== null) {
            return $aiAssessment['action'];
        }

        $maxScore = max(array_column($flags, 'score'));

        return match (true) {
            $maxScore >= 0.8 => 'reject',
            $maxScore >= 0.5 => 'flag',
            default          => 'clear',
        };
    }
}