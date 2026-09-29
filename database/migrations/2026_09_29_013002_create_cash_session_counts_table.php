<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_session_counts', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('cash_session_id');
            $table->foreignId('counted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->decimal('counted_amount', 19, 2);
            $table->json('denominations')->nullable();
            $table->timestamp('cutoff_at');
            $table->unsignedBigInteger('movement_high_watermark')->default(0);
            $table->unsignedInteger('pending_server_sale_count')->default(0);
            $table->unsignedInteger('pending_offline_sale_count')->default(0);
            $table->timestamps();

            $table->unique(['cash_session_id', 'idempotency_key']);
            $table->index(['cash_session_id', 'created_at']);
            $table->foreign(['merchant_id', 'store_id', 'cash_session_id'], 'cash_counts_session_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('cash_sessions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_session_counts');
    }
};
