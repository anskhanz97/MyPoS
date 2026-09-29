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
        Schema::create('variant_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_book_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->string('customer_group', 80)->nullable();
            $table->string('customer_group_key', 80)->storedAs("coalesce(`customer_group`, '')");
            $table->decimal('unit_price', 19, 4);
            $table->decimal('tax_rate_percent', 12, 6)->default(0);
            $table->boolean('tax_inclusive')->default(false);
            $table->timestamps();

            $table->unique(['price_book_version_id', 'product_variant_id', 'customer_group_key'], 'variant_prices_version_variant_group_unique');
            $table->foreign(['merchant_id', 'price_book_version_id'], 'variant_prices_version_owner_fk')->references(['merchant_id', 'id'])->on('price_book_versions');
            $table->foreign(['merchant_id', 'product_variant_id'], 'variant_prices_variant_owner_fk')->references(['merchant_id', 'id'])->on('product_variants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('variant_prices');
    }
};
