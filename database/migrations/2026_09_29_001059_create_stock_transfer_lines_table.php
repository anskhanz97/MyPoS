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
        Schema::create('stock_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('stock_transfer_id');
            $table->foreignId('inventory_variant_mapping_id')->nullable();
            $table->foreignId('source_product_variant_id');
            $table->foreignId('destination_product_variant_id');
            $table->foreignId('source_product_batch_id')->nullable();
            $table->decimal('factor_to_destination_base', 19, 6);
            $table->decimal('requested_source_quantity_base', 19, 6);
            $table->decimal('dispatched_source_quantity_base', 19, 6)->default(0);
            $table->decimal('expected_destination_quantity_base', 19, 6)->default(0);
            $table->decimal('received_destination_quantity_base', 19, 6)->default(0);
            $table->decimal('short_destination_quantity_base', 19, 6)->default(0);
            $table->decimal('excess_destination_quantity_base', 19, 6)->default(0);
            $table->timestamps();
            $table->unique(['merchant_id', 'id']);
            $table->unique(['merchant_id', 'stock_transfer_id', 'id']);
            $table->foreign(['merchant_id', 'stock_transfer_id'], 'stock_transfer_lines_transfer_owner_fk')->references(['merchant_id', 'id'])->on('stock_transfers')->cascadeOnDelete();
            $table->foreign(['merchant_id', 'inventory_variant_mapping_id'], 'stock_transfer_lines_mapping_owner_fk')->references(['merchant_id', 'id'])->on('inventory_variant_mappings');
            $table->foreign(['merchant_id', 'source_product_variant_id'], 'stock_transfer_lines_source_variant_fk')->references(['merchant_id', 'id'])->on('product_variants');
            $table->foreign(['merchant_id', 'destination_product_variant_id'], 'stock_transfer_lines_destination_variant_fk')->references(['merchant_id', 'id'])->on('product_variants');
            $table->foreign(['merchant_id', 'source_product_batch_id'], 'stock_transfer_lines_source_batch_fk')->references(['merchant_id', 'id'])->on('product_batches');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_lines');
    }
};
