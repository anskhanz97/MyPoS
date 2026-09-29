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
        Schema::create('batch_stock_balances', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('inventory_location_id');
            $table->foreignId('product_variant_id');
            $table->foreignId('product_batch_id');
            $table->decimal('quantity_base', 19, 6)->default(0);
            $table->unsignedBigInteger('movement_count')->default(0);
            $table->foreignId('last_stock_movement_id')->nullable();
            $table->unsignedBigInteger('projection_version')->default(0);
            $table->timestamp('last_reconciled_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'store_id', 'inventory_location_id', 'product_variant_id', 'product_batch_id'], 'batch_stock_balances_dimension_unique');
            $table->index(['merchant_id', 'store_id', 'product_variant_id', 'quantity_base'], 'batch_stock_balances_availability_idx');
            $table->foreign(['merchant_id', 'store_id', 'inventory_location_id'], 'batch_stock_balances_location_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('inventory_locations');
            $table->foreign(['merchant_id', 'product_variant_id'], 'batch_stock_balances_variant_owner_fk')->references(['merchant_id', 'id'])->on('product_variants');
            $table->foreign(['merchant_id', 'product_variant_id', 'product_batch_id'], 'batch_stock_balances_batch_owner_fk')->references(['merchant_id', 'product_variant_id', 'id'])->on('product_batches');
            $table->foreign(['merchant_id', 'store_id', 'last_stock_movement_id'], 'batch_stock_balances_last_movement_fk')->references(['merchant_id', 'store_id', 'id'])->on('stock_movements');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_stock_balances');
    }
};
