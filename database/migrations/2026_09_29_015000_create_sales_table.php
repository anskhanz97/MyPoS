<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('register_id');
            $table->foreignId('cash_session_id');
            $table->foreignId('cart_id');
            $table->foreignId('cart_quote_id');
            $table->foreignId('customer_id')->nullable()->constrained('operational_customers')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 40);
            $table->string('state', 30)->default('finalized');
            $table->uuid('client_transaction_id');
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->string('pricing_version', 40);
            $table->unsignedInteger('cart_version');
            $table->unsignedInteger('quote_version');
            $table->date('business_date');
            $table->string('currency', 3)->default('PKR');
            $table->decimal('subtotal', 19, 2);
            $table->decimal('discount_total', 19, 2);
            $table->decimal('net_total', 19, 2);
            $table->decimal('tax_total', 19, 2);
            $table->decimal('total', 19, 2);
            $table->decimal('cash_change', 19, 2)->default(0);
            $table->json('customer_snapshot')->nullable();
            $table->timestamp('finalized_at');
            $table->timestamps();
            $table->unique(['merchant_id', 'number']);
            $table->unique(['merchant_id', 'client_transaction_id']);
            $table->unique(['merchant_id', 'idempotency_key']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->index(['store_id', 'business_date', 'id']);
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'store_id', 'register_id'])->references(['merchant_id', 'store_id', 'id'])->on('registers');
            $table->foreign(['merchant_id', 'store_id', 'cash_session_id'], 'sales_cash_session_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('cash_sessions');
            $table->foreign(['merchant_id', 'store_id', 'cart_id'])->references(['merchant_id', 'store_id', 'id'])->on('carts');
            $table->foreign(['merchant_id', 'store_id', 'cart_quote_id'], 'sales_quote_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('cart_quotes');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
