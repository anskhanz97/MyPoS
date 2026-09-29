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
        Schema::create('supplier_charge_entries', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('supplier_id');
            $table->foreignId('purchase_receipt_id');
            $table->foreignId('reverses_entry_id')->nullable();
            $table->string('entry_kind', 20);
            $table->string('currency', 3)->default('PKR');
            $table->decimal('amount', 19, 2);
            $table->string('source_reference', 120);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['merchant_id', 'store_id', 'purchase_receipt_id', 'entry_kind'], 'supplier_charge_receipt_kind_unique');
            $table->index(['merchant_id', 'supplier_id', 'occurred_at'], 'supplier_charge_supplier_idx');
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'supplier_id'])->references(['merchant_id', 'id'])->on('suppliers');
            $table->foreign(['merchant_id', 'store_id', 'purchase_receipt_id'], 'supplier_charge_receipt_fk')->references(['merchant_id', 'store_id', 'id'])->on('purchase_receipts');
            $table->foreign('reverses_entry_id')->references('id')->on('supplier_charge_entries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_charge_entries');
    }
};
