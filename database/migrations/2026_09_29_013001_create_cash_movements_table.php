<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('cash_session_id');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 40);
            $table->decimal('signed_amount', 19, 2);
            $table->string('reason', 500);
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->string('source_type', 80)->nullable();
            $table->binary('source_public_id', 16, true)->nullable();
            $table->string('source_key', 160)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['cash_session_id', 'idempotency_key']);
            $table->unique(['merchant_id', 'store_id', 'source_key']);
            $table->index(['cash_session_id', 'occurred_at']);
            $table->foreign(['merchant_id', 'store_id', 'cash_session_id'])->references(['merchant_id', 'store_id', 'id'])->on('cash_sessions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
