<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('supplier_id');
            $table->foreignId('supplier_payment_id');
            $table->foreignId('supplier_charge_entry_id');
            $table->foreignId('reverses_allocation_id')->nullable();
            $table->string('entry_kind', 20)->default('allocation');
            $table->decimal('amount', 19, 2);
            $table->timestamps();

            $table->unique(['supplier_payment_id', 'supplier_charge_entry_id', 'entry_kind'], 'supplier_payment_charge_kind_unique');
            $table->foreign(['merchant_id', 'store_id', 'supplier_payment_id'], 'supplier_allocations_payment_fk')->references(['merchant_id', 'store_id', 'id'])->on('supplier_payments');
            $table->foreign('supplier_charge_entry_id', 'supplier_allocations_charge_fk')->references('id')->on('supplier_charge_entries');
            $table->foreign('reverses_allocation_id', 'supplier_allocations_reversal_fk')->references('id')->on('supplier_payment_allocations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payment_allocations');
    }
};
