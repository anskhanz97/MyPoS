<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('purchase_order_id');
            $table->foreignId('product_variant_id');
            $table->foreignId('purchase_unit_id');
            $table->string('product_name_snapshot', 160);
            $table->string('sku_snapshot', 80)->nullable();
            $table->string('unit_symbol_snapshot', 20);
            $table->decimal('ordered_quantity_uom', 19, 6);
            $table->decimal('factor_to_base', 19, 6);
            $table->decimal('ordered_quantity_base', 19, 6);
            $table->decimal('received_quantity_base', 19, 6)->default(0);
            $table->decimal('returned_quantity_base', 19, 6)->default(0);
            $table->decimal('cancelled_quantity_base', 19, 6)->default(0);
            $table->decimal('expected_unit_cost', 19, 6);
            $table->string('tax_reference', 80)->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'store_id', 'purchase_order_id', 'id'], 'po_lines_owner_unique');
            $table->unique(['purchase_order_id', 'product_variant_id', 'purchase_unit_id'], 'purchase_order_lines_dimension_unique');
            $table->foreign(['merchant_id', 'store_id', 'purchase_order_id'], 'purchase_order_lines_order_fk')->references(['merchant_id', 'store_id', 'id'])->on('purchase_orders')->cascadeOnDelete();
            $table->foreign(['merchant_id', 'product_variant_id'], 'purchase_order_lines_variant_fk')->references(['merchant_id', 'id'])->on('product_variants');
            $table->foreign(['merchant_id', 'purchase_unit_id'], 'purchase_order_lines_unit_fk')->references(['merchant_id', 'id'])->on('unit_of_measures');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
