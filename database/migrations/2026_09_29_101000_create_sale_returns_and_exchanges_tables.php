<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('register_id');
            $table->foreignId('cash_session_id');
            $table->foreignId('sale_id');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approval_id')->nullable()->constrained('approvals')->nullOnDelete();
            $table->string('number', 40);
            $table->string('state', 30);
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->string('reason', 500);
            $table->string('late_reason', 500)->nullable();
            $table->unsignedTinyInteger('policy_window_days');
            $table->boolean('late_exception')->default(false);
            $table->date('business_date');
            $table->string('currency', 3)->default('PKR');
            $table->decimal('subtotal', 19, 2);
            $table->decimal('discount_total', 19, 2);
            $table->decimal('net_total', 19, 2);
            $table->decimal('tax_total', 19, 2);
            $table->decimal('total', 19, 2);
            $table->json('receipt_payload');
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->unique(['merchant_id', 'number']);
            $table->unique(['merchant_id', 'idempotency_key']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->index(['sale_id', 'state']);
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'store_id', 'register_id'])->references(['merchant_id', 'store_id', 'id'])->on('registers');
            $table->foreign(['merchant_id', 'store_id', 'cash_session_id'], 'sale_returns_cash_session_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('cash_sessions');
            $table->foreign(['merchant_id', 'store_id', 'sale_id'])->references(['merchant_id', 'store_id', 'id'])->on('sales');
        });

        Schema::create('sale_return_lines', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('sale_return_id');
            $table->foreignId('sale_line_id');
            $table->unsignedInteger('sequence');
            $table->string('disposition', 30);
            $table->decimal('quantity_uom', 19, 6);
            $table->decimal('quantity_base', 19, 6);
            $table->decimal('cumulative_quantity_uom', 19, 6);
            $table->decimal('pre_discount', 19, 2);
            $table->decimal('discount', 19, 2);
            $table->decimal('net', 19, 2);
            $table->decimal('tax', 19, 2);
            $table->decimal('gross', 19, 2);
            $table->json('original_allocations')->nullable();
            $table->timestamps();
            $table->unique(['sale_return_id', 'sale_line_id']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->index(['sale_line_id', 'id']);
            $table->foreign(['merchant_id', 'store_id', 'sale_return_id'], 'return_lines_return_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('sale_returns');
            $table->foreign(['merchant_id', 'store_id', 'sale_line_id'], 'return_lines_sale_line_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('sale_lines');
        });

        Schema::create('sale_return_tenders', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('sale_return_id');
            $table->uuid('client_tender_id');
            $table->string('type', 30);
            $table->string('state', 30);
            $table->decimal('amount', 19, 2);
            $table->string('external_reference', 200)->nullable();
            $table->string('confirmation_source', 30);
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['sale_return_id', 'client_tender_id']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->foreign(['merchant_id', 'store_id', 'sale_return_id'], 'return_tenders_return_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('sale_returns');
        });

        Schema::create('sale_return_stock_effects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('sale_return_line_id');
            $table->foreignId('original_stock_movement_id')->constrained('stock_movements');
            $table->foreignId('return_stock_movement_id')->unique()->constrained('stock_movements');
            $table->decimal('quantity_base', 19, 6);
            $table->timestamps();
            $table->unique(['sale_return_line_id', 'original_stock_movement_id'], 'return_stock_effect_origin_unique');
            $table->foreign(['merchant_id', 'store_id', 'sale_return_line_id'], 'return_stock_effect_line_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('sale_return_lines');
        });

        Schema::create('sale_exchanges', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('register_id');
            $table->foreignId('sale_return_id');
            $table->foreignId('new_sale_id');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 40);
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->decimal('difference_amount', 19, 2);
            $table->string('difference_direction', 20);
            $table->timestamp('posted_at');
            $table->timestamps();
            $table->unique(['merchant_id', 'idempotency_key']);
            $table->unique('sale_return_id');
            $table->unique('new_sale_id');
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'store_id', 'register_id'])->references(['merchant_id', 'store_id', 'id'])->on('registers');
            $table->foreign(['merchant_id', 'store_id', 'sale_return_id'], 'exchanges_return_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('sale_returns');
            $table->foreign(['merchant_id', 'store_id', 'new_sale_id'], 'exchanges_sale_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('sales');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_exchanges');
        Schema::dropIfExists('sale_return_stock_effects');
        Schema::dropIfExists('sale_return_tenders');
        Schema::dropIfExists('sale_return_lines');
        Schema::dropIfExists('sale_returns');
    }
};
