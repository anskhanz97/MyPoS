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
        Schema::create('inventory_variant_mappings', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('source_store_id');
            $table->foreignId('destination_store_id');
            $table->foreignId('source_product_variant_id');
            $table->foreignId('destination_product_variant_id');
            $table->decimal('factor_to_destination_base', 19, 6);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['merchant_id', 'source_store_id', 'destination_store_id', 'source_product_variant_id'], 'inventory_variant_mappings_route_unique');
            $table->unique(['merchant_id', 'id']);
            $table->foreign(['merchant_id', 'source_store_id'], 'inventory_variant_mappings_source_store_fk')->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'destination_store_id'], 'inventory_variant_mappings_destination_store_fk')->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'source_product_variant_id'], 'inventory_variant_mappings_source_variant_fk')->references(['merchant_id', 'id'])->on('product_variants');
            $table->foreign(['merchant_id', 'destination_product_variant_id'], 'inventory_variant_mappings_destination_variant_fk')->references(['merchant_id', 'id'])->on('product_variants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_variant_mappings');
    }
};
