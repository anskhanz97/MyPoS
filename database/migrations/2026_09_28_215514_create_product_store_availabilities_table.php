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
        Schema::create('product_store_availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('product_id');
            $table->boolean('is_available')->default(true);
            $table->timestamps();
            $table->unique(['store_id', 'product_id']);
            $table->foreign(['merchant_id', 'store_id'], 'availability_store_owner_fk')->references(['merchant_id', 'id'])->on('stores')->cascadeOnDelete();
            $table->foreign(['merchant_id', 'product_id'], 'availability_product_owner_fk')->references(['merchant_id', 'id'])->on('products')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_store_availabilities');
    }
};
