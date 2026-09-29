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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id')->nullable();
            $table->unsignedBigInteger('scope_store_id')->storedAs('coalesce(`store_id`, 0)');
            $table->foreignId('product_id');
            $table->foreignId('base_unit_id');
            $table->string('sku', 80);
            $table->string('name', 160);
            $table->json('attributes')->nullable();
            $table->boolean('is_weighted')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'id']);
            $table->unique(['merchant_id', 'scope_store_id', 'sku']);
            $table->foreign(['merchant_id', 'product_id'], 'variants_product_owner_fk')->references(['merchant_id', 'id'])->on('products')->cascadeOnDelete();
            $table->foreign(['merchant_id', 'base_unit_id'], 'variants_unit_owner_fk')->references(['merchant_id', 'id'])->on('unit_of_measures');
            $table->index(['merchant_id', 'scope_store_id', 'archived_at', 'name'], 'variants_catalog_search_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
