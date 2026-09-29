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
        Schema::create('extra_options', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('extra_group_id');
            $table->string('name', 120);
            $table->decimal('price_delta', 18, 2)->default(0);
            $table->foreignId('stock_variant_id')->nullable();
            $table->decimal('stock_quantity_base', 18, 6)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['merchant_id', 'id']);
            $table->foreign(['merchant_id', 'extra_group_id'], 'extra_options_group_owner_fk')->references(['merchant_id', 'id'])->on('extra_groups')->cascadeOnDelete();
            $table->foreign(['merchant_id', 'stock_variant_id'], 'extra_options_stock_owner_fk')->references(['merchant_id', 'id'])->on('product_variants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extra_options');
    }
};
