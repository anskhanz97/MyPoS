<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_lines', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('cart_id');
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
            $table->decimal('manual_discount', 19, 2)->default(0);
            $table->string('scan_code', 64)->nullable();
            $table->unsignedInteger('scan_count')->default(0);
            $table->char('configuration_hash', 64);
            $table->timestamps();

            $table->unique(['cart_id', 'sequence']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->index(['cart_id', 'product_variant_id']);
            $table->foreign(['merchant_id', 'store_id', 'cart_id'])->references(['merchant_id', 'store_id', 'id'])->on('carts');
            $table->foreign(['merchant_id', 'product_variant_id'])->references(['merchant_id', 'id'])->on('product_variants');
            $table->foreign(['merchant_id', 'unit_id'])->references(['merchant_id', 'id'])->on('unit_of_measures');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_lines');
    }
};
