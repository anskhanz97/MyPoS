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
        Schema::create('purchase_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('purchase_receipt_id');
            $table->foreignId('purchase_order_id');
            $table->foreignId('purchase_order_line_id');
            $table->foreignId('product_variant_id');
            $table->foreignId('purchase_unit_id');
            $table->foreignId('product_batch_id')->nullable();
            $table->foreignId('stock_movement_id');
            $table->foreignId('inventory_cost_entry_id');
            $table->string('product_name_snapshot', 160);
            $table->string('sku_snapshot', 80)->nullable();
            $table->string('unit_symbol_snapshot', 20);
            $table->decimal('received_quantity_uom', 19, 6);
            $table->decimal('factor_to_base', 19, 6);
            $table->decimal('received_quantity_base', 19, 6);
            $table->decimal('unit_cost_uom', 19, 6);
            $table->decimal('unit_cost_base', 19, 6);
            $table->decimal('landed_cost_total', 25, 6)->default(0);
            $table->decimal('recoverable_tax_total', 25, 6)->default(0);
            $table->decimal('inventory_unit_cost_base', 19, 6);
            $table->decimal('inventory_value', 25, 6);
            $table->timestamps();

            $table->unique(['merchant_id', 'store_id', 'purchase_receipt_id', 'id'], 'receipt_lines_owner_unique');
            $table->unique(['purchase_receipt_id', 'purchase_order_line_id', 'product_batch_id'], 'receipt_lines_allocation_unique');
            $table->foreign(['merchant_id', 'store_id', 'purchase_receipt_id'], 'receipt_lines_receipt_fk')->references(['merchant_id', 'store_id', 'id'])->on('purchase_receipts');
            $table->foreign(['merchant_id', 'store_id', 'purchase_order_id', 'purchase_order_line_id'], 'receipt_lines_po_line_fk')->references(['merchant_id', 'store_id', 'purchase_order_id', 'id'])->on('purchase_order_lines');
            $table->foreign(['merchant_id', 'product_variant_id'], 'receipt_lines_variant_fk')->references(['merchant_id', 'id'])->on('product_variants');
            $table->foreign(['merchant_id', 'purchase_unit_id'], 'receipt_lines_unit_fk')->references(['merchant_id', 'id'])->on('unit_of_measures');
            $table->foreign(['merchant_id', 'product_variant_id', 'product_batch_id'], 'receipt_lines_batch_fk')->references(['merchant_id', 'product_variant_id', 'id'])->on('product_batches');
            $table->foreign('stock_movement_id')->references('id')->on('stock_movements');
            $table->foreign('inventory_cost_entry_id')->references('id')->on('inventory_cost_entries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_receipt_lines');
    }
};
