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
        Schema::create('stocktake_lines', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('stocktake_id');
            $table->foreignId('product_variant_id');
            $table->foreignId('product_batch_id')->nullable();
            $table->decimal('baseline_quantity_base', 19, 6);
            $table->decimal('counted_quantity_base', 19, 6)->nullable();
            $table->unsignedBigInteger('count_cutoff_movement_id')->nullable();
            $table->decimal('expected_at_count_quantity_base', 19, 6)->nullable();
            $table->decimal('discrepancy_quantity_base', 19, 6)->nullable();
            $table->foreignId('posted_stock_movement_id')->nullable();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();
            $table->unsignedBigInteger('batch_slot')->storedAs('coalesce(`product_batch_id`, 0)');
            $table->unique(['merchant_id', 'store_id', 'stocktake_id', 'product_variant_id', 'batch_slot'], 'stocktake_lines_dimension_unique');
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->foreign(['merchant_id', 'store_id', 'stocktake_id'], 'stocktake_lines_stocktake_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('stocktakes')->cascadeOnDelete();
            $table->foreign(['merchant_id', 'product_variant_id'], 'stocktake_lines_variant_owner_fk')->references(['merchant_id', 'id'])->on('product_variants');
            $table->foreign(['merchant_id', 'product_batch_id'], 'stocktake_lines_batch_owner_fk')->references(['merchant_id', 'id'])->on('product_batches');
            $table->foreign(['merchant_id', 'store_id', 'posted_stock_movement_id'], 'stocktake_lines_posted_movement_fk')->references(['merchant_id', 'store_id', 'id'])->on('stock_movements');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocktake_lines');
    }
};
