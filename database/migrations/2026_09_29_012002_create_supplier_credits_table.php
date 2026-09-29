<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_credits', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('supplier_id');
            $table->foreignId('purchase_return_id');
            $table->foreignId('reverses_credit_id')->nullable();
            $table->string('entry_kind', 20)->default('credit');
            $table->string('status', 20)->default('expected');
            $table->string('currency', 3)->default('PKR');
            $table->decimal('amount', 19, 2);
            $table->string('supplier_reference', 120)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['purchase_return_id', 'entry_kind']);
            $table->index(['merchant_id', 'supplier_id', 'status', 'occurred_at'], 'supplier_credits_statement_idx');
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'supplier_id'])->references(['merchant_id', 'id'])->on('suppliers');
            $table->foreign(['merchant_id', 'store_id', 'purchase_return_id'], 'supplier_credits_return_fk')->references(['merchant_id', 'store_id', 'id'])->on('purchase_returns');
            $table->foreign('reverses_credit_id')->references('id')->on('supplier_credits');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_credits');
    }
};
