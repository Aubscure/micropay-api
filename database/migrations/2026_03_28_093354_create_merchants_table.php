<?php
// database/migrations/2026_03_28_093354_create_merchants_table.php
// NOTE: timestamp is 093354 — one second BEFORE transactions (093355)

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            // UUID primary key — same reason as transactions
            $table->uuid('id')->primary();

            // The user account that owns this merchant profile
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            $table->string('business_name');
            $table->string('business_type')->nullable(); // sari-sari, market stall, etc.

            // Soft deletes — never hard-delete merchant records
            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
