<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_session_handovers', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('cash_session_id');
            $table->foreignId('from_user_id')->constrained('users');
            $table->foreignId('to_user_id')->constrained('users');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->string('note', 500)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['cash_session_id', 'idempotency_key']);
            $table->foreign(['merchant_id', 'store_id', 'cash_session_id'], 'cash_handovers_session_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('cash_sessions');
        });

        Schema::create('cash_variance_reviews', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('cash_session_id');
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->string('decision', 20);
            $table->string('reason', 500);
            $table->decimal('expected_amount', 19, 2);
            $table->decimal('counted_amount', 19, 2);
            $table->decimal('variance_amount', 19, 2);
            $table->timestamp('reviewed_at');
            $table->timestamps();
            $table->unique(['cash_session_id', 'idempotency_key']);
            $table->index(['cash_session_id', 'reviewed_at']);
            $table->foreign(['merchant_id', 'store_id', 'cash_session_id'], 'cash_reviews_session_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('cash_sessions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_variance_reviews');
        Schema::dropIfExists('cash_session_handovers');
    }
};
