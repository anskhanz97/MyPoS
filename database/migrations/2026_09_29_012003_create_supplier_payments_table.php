<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('supplier_id');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reverses_payment_id')->nullable();
            $table->string('number', 40);
            $table->string('entry_kind', 20)->default('payment');
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->string('currency', 3)->default('PKR');
            $table->decimal('amount', 19, 2);
            $table->string('payment_method', 40);
            $table->string('reference', 120);
            $table->string('notes', 1000)->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();

            $table->unique(['merchant_id', 'number']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->unique(['merchant_id', 'store_id', 'idempotency_key']);
            $table->unique('reverses_payment_id');
            $table->index(['merchant_id', 'supplier_id', 'paid_at'], 'supplier_payments_statement_idx');
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'supplier_id'])->references(['merchant_id', 'id'])->on('suppliers');
            $table->foreign('reverses_payment_id')->references('id')->on('supplier_payments');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
