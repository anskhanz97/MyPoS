<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_transfer_receipts', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('stock_transfer_id');
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->string('resolution', 24)->default('partial');
            $table->string('reason', 500)->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->unique(['merchant_id', 'id']);
            $table->unique(['merchant_id', 'stock_transfer_id', 'idempotency_key'], 'stock_transfer_receipts_idempotency_unique');
            $table->foreign(['merchant_id', 'stock_transfer_id'], 'stock_transfer_receipts_transfer_owner_fk')->references(['merchant_id', 'id'])->on('stock_transfers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_receipts');
    }
};
