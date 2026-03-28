<?php
// app/Services/FraudDetection/AiAnalysisAgent.php

namespace App\Services\FraudDetection;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Uses Groq API (free tier) to analyze suspicious transactions.
 * Only called when the rule engine flags something — saves API quota.
 *
 * Groq uses llama-3.3-70b model — fast and free for low volumes.
 * Sign up at: https://console.groq.com (no credit card needed)
 */
class AiAnalysisAgent
{
    /**
     * The system prompt that turns the LLM into a fraud analyst.
     * This is the "agent definition" — it tells the AI its role,
     * constraints, and expected output format.
     */
    private const SYSTEM_PROMPT = <<<PROMPT
    You are a financial fraud detection specialist for a Philippine MSME payment gateway.
    Your role is to analyze flagged transactions and provide risk assessments.

    You will receive:
    1. Transaction details (amount, time, merchant, payment method)
    2. Triggered fraud rules and their scores

    You must respond with ONLY valid JSON in this exact format:
    {
        "risk_level": "low|medium|high|critical",
        "risk_score": 0.0,
        "action": "clear|flag|reject",
        "reasoning": "Brief explanation in 2-3 sentences.",
        "confidence": 0.0
    }

    Guidelines:
    - risk_score: 0.0 (safe) to 1.0 (definite fraud)
    - action=clear: transaction is safe to process
    - action=flag: needs human review before settling
    - action=reject: reject immediately
    - Consider Philippine market context (MSMEs, markets, sari-sari stores)
    - Be conservative — it is better to flag than to miss fraud
    PROMPT;

    /**
     * Analyze a suspicious transaction using the Groq LLM.
     *
     * @param Transaction $transaction The suspicious transaction
     * @param array       $flags       Rules that were triggered
     * @return array      The AI's assessment
     */
    public function analyze(Transaction $transaction, array $flags): array
    {
        // Build a structured description of the transaction for the AI
        $prompt = $this->buildPrompt($transaction, $flags);

        try {
            // Call Groq API — free tier available at console.groq.com
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . config('services.groq.api_key'),
                'Content-Type'  => 'application/json',
            ])
            ->timeout(10) // 10 second timeout — don't hold up the queue
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => 'llama-3.3-70b-versatile', // Fast, free model
                'messages'    => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => 0.1,   // Low temperature = more consistent, less creative
                'max_tokens'  => 300,   // Keep responses short (saves quota)
                'response_format' => ['type' => 'json_object'], // Force JSON output
            ]);

            if (!$response->successful()) {
                throw new \Exception("Groq API error: " . $response->status());
            }

            // Extract the JSON content from the response
            $content = $response->json('choices.0.message.content');
            $assessment = json_decode($content, true);

            if (!$assessment || !isset($assessment['action'])) {
                throw new \Exception("Invalid AI response format");
            }

            return $assessment;

        } catch (\Exception $e) {
            // If AI call fails, log it and fall back to rule-engine decision
            Log::warning('AI fraud analysis failed, falling back to rules', [
                'error'          => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);

            // Fallback: calculate score from rules alone
            $avgScore = count($flags) > 0
                ? array_sum(array_column($flags, 'score')) / count($flags)
                : 0.5;

            return [
                'risk_level' => $avgScore > 0.7 ? 'high' : 'medium',
                'risk_score' => $avgScore,
                'action'     => $avgScore > 0.7 ? 'flag' : 'clear',
                'reasoning'  => 'AI analysis unavailable. Based on rule engine scores.',
                'confidence' => 0.5,
            ];
        }
    }

    /**
     * Build the user prompt with transaction context.
     * The clearer the prompt, the better the AI analysis.
     */
    private function buildPrompt(Transaction $transaction, array $flags): string
    {
        $flagDescriptions = collect($flags)
            ->map(fn($f) => "- {$f['rule']} (score: {$f['score']}): {$f['reason']}")
            ->join("\n");

        return <<<EOT
        TRANSACTION DETAILS:
        - ID: {$transaction->id}
        - Amount: PHP {$transaction->amount_php}
        - Currency: {$transaction->currency}
        - Payment Method: {$transaction->payment_method}
        - Time: {$transaction->created_at->setTimezone('Asia/Manila')->format('Y-m-d H:i:s')} (Manila)
        - Offline Transaction: {$transaction->was_offline}

        TRIGGERED FRAUD RULES:
        {$flagDescriptions}

        Please analyze this transaction and provide your risk assessment.
        EOT;
    }
}