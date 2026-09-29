<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('supplier_id');
            $table->foreignId('purchase_receipt_id');
            $table->foreignId('inventory_location_id');
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 40);
            $table->string('status', 20)->default('posted');
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->string('reason', 500);
            $table->string('supplier_credit_reference', 120)->nullable();
            $table->string('credit_status', 20)->default('expected');
            $table->decimal('supplier_credit_total', 19, 2);
            $table->timestamp('returned_at');
            $table->timestamp('posted_at');
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reversal_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'number']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->unique(['merchant_id', 'store_id', 'idempotency_key']);
            $table->index(['merchant_id', 'supplier_id', 'returned_at']);
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'supplier_id'])->references(['merchant_id', 'id'])->on('suppliers');
            $table->foreign(['merchant_id', 'store_id', 'purchase_receipt_id'], 'purchase_returns_receipt_fk')->references(['merchant_id', 'store_id', 'id'])->on('purchase_receipts');
            $table->foreign(['merchant_id', 'store_id', 'inventory_location_id'], 'purchase_returns_location_fk')->references(['merchant_id', 'store_id', 'id'])->on('inventory_locations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_returns');
    }
};
