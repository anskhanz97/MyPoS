<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_return_lines', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('purchase_return_id');
            $table->foreignId('purchase_receipt_line_id');
            $table->foreignId('product_variant_id');
            $table->foreignId('product_batch_id')->nullable();
            $table->foreignId('stock_movement_id');
            $table->foreignId('inventory_cost_entry_id');
            $table->string('product_name_snapshot', 160);
            $table->string('sku_snapshot', 80)->nullable();
            $table->string('unit_symbol_snapshot', 20);
            $table->decimal('returned_quantity_uom', 19, 6);
            $table->decimal('factor_to_base', 19, 6);
            $table->decimal('returned_quantity_base', 19, 6);
            $table->decimal('source_credit_unit_base', 19, 6);
            $table->decimal('supplier_credit_amount', 19, 2);
            $table->decimal('valuation_unit_cost', 19, 6)->nullable();
            $table->timestamps();

            $table->unique(['purchase_return_id', 'purchase_receipt_line_id'], 'purchase_return_source_line_unique');
            $table->foreign(['merchant_id', 'store_id', 'purchase_return_id'], 'purchase_return_lines_return_fk')->references(['merchant_id', 'store_id', 'id'])->on('purchase_returns');
            $table->foreign('purchase_receipt_line_id')->references('id')->on('purchase_receipt_lines');
            $table->foreign(['merchant_id', 'product_variant_id'])->references(['merchant_id', 'id'])->on('product_variants');
            $table->foreign(['merchant_id', 'product_variant_id', 'product_batch_id'], 'purchase_return_lines_batch_fk')->references(['merchant_id', 'product_variant_id', 'id'])->on('product_batches');
            $table->foreign('stock_movement_id')->references('id')->on('stock_movements');
            $table->foreign('inventory_cost_entry_id')->references('id')->on('inventory_cost_entries');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_lines');
    }
};
