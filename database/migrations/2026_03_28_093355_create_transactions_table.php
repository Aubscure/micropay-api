<?php
// database/migrations/2025_01_01_000003_create_transactions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the transactions table.
     * This is the core table of the entire system.
     * All payments, pending or settled, live here.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            // Primary key — UUID is better than integer for payments.
            // UUIDs are globally unique, harder to enumerate, and work
            // offline (the client can generate one without hitting the server).
            $table->uuid('id')->primary();

            // Links to the merchant receiving the money
            $table->foreignUuid('merchant_id')->constrained('merchants')->onDelete('cascade');

            // The customer's user ID (nullable = anonymous payments possible)
            $table->foreignId('customer_id')->nullable()->constrained('users')->onDelete('set null');

            // Amount in the smallest currency unit (centavos/cents).
            // NEVER store money as float — floating point causes rounding errors.
            // 100000 = PHP 1,000.00 (amount * 100)
            $table->unsignedBigInteger('amount_centavos');

            // ISO 4217 currency code: PHP, USD, SGD, etc.
            $table->string('currency', 3)->default('PHP');

            // Transaction lifecycle:
            // pending → fraud_check → cleared → settled
            //                      ↘ flagged (requires manual review)
            $table->enum('status', [
                'pending',
                'fraud_check',
                'cleared',
                'settled',
                'flagged',
                'rejected',
                'refunded',
            ])->default('pending');

            // Payment method used by the customer
            $table->enum('payment_method', [
                'qr_code',
                'nfc',
                'manual_entry',
            ])->default('qr_code');

            // If this was initiated offline and synced later.
            // Important for audit trail.
            $table->boolean('was_offline')->default(false);

            // The exact time the transaction happened on the device.
            // Different from created_at (server receipt time).
            $table->timestamp('initiated_at')->nullable();

            // Optional free-text note from merchant or customer
            $table->text('notes')->nullable();

            // JSON blob for extra data: geolocation, device fingerprint, etc.
            // Stored as JSON so we don't need separate columns for every field.
            $table->json('metadata')->nullable();

            // Soft deletes — never permanently delete financial records
            $table->softDeletes();

            // Laravel timestamps: created_at, updated_at
            $table->timestamps();

            // Indexes for common query patterns
            $table->index(['merchant_id', 'status']);      // Merchant dashboard queries
            $table->index(['status', 'created_at']);        // Admin monitoring
            $table->index('initiated_at');                  // Time-range fraud analysis
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};