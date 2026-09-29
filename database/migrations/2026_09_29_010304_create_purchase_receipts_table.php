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
        Schema::create('purchase_receipts', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('purchase_order_id');
            $table->foreignId('inventory_location_id');
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 40);
            $table->string('status', 20)->default('posted');
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->unsignedBigInteger('expected_order_version');
            $table->string('supplier_reference', 120)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamp('received_at');
            $table->timestamp('posted_at');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason', 500)->nullable();
            $table->decimal('merchandise_total', 25, 6);
            $table->decimal('landed_cost_total', 25, 6)->default(0);
            $table->decimal('recoverable_tax_total', 25, 6)->default(0);
            $table->decimal('supplier_charge_total', 25, 6);
            $table->string('valuation_timing', 32)->default('current_sequence');
            $table->timestamps();

            $table->unique(['merchant_id', 'number']);
            $table->unique(['merchant_id', 'store_id', 'id'], 'purchase_receipts_owner_unique');
            $table->unique(['merchant_id', 'store_id', 'idempotency_key'], 'purchase_receipts_idempotency_unique');
            $table->index(['merchant_id', 'store_id', 'purchase_order_id', 'received_at'], 'purchase_receipts_order_idx');
            $table->foreign(['merchant_id', 'store_id', 'purchase_order_id'], 'purchase_receipts_order_fk')->references(['merchant_id', 'store_id', 'id'])->on('purchase_orders');
            $table->foreign(['merchant_id', 'store_id', 'inventory_location_id'], 'purchase_receipts_location_fk')->references(['merchant_id', 'store_id', 'id'])->on('inventory_locations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_receipts');
    }
};
