<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_lines', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('sale_id');
            $table->foreignId('product_variant_id');
            $table->foreignId('unit_id');
            $table->foreignId('batch_selection_snapshot_id')->nullable()->constrained('batch_selection_snapshots');
            $table->unsignedInteger('sequence');
            $table->string('product_name_snapshot', 160);
            $table->string('variant_name_snapshot', 120)->nullable();
            $table->string('sku_snapshot', 80);
            $table->string('unit_symbol_snapshot', 20);
            $table->decimal('quantity_uom', 19, 6);
            $table->decimal('factor_to_base', 19, 6);
            $table->decimal('quantity_base', 19, 6);
            $table->decimal('unit_price', 19, 4);
            $table->decimal('pre_discount', 19, 2);
            $table->decimal('discount', 19, 2);
            $table->decimal('net', 19, 2);
            $table->decimal('tax', 19, 2);
            $table->decimal('gross', 19, 2);
            $table->json('allocations')->nullable();
            $table->timestamps();
            $table->unique(['sale_id', 'sequence']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->foreign(['merchant_id', 'store_id', 'sale_id'])->references(['merchant_id', 'store_id', 'id'])->on('sales');
            $table->foreign(['merchant_id', 'product_variant_id'])->references(['merchant_id', 'id'])->on('product_variants');
            $table->foreign(['merchant_id', 'unit_id'])->references(['merchant_id', 'id'])->on('unit_of_measures');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_lines');
    }
};
