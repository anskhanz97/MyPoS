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
        Schema::create('unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('product_variant_id');
            $table->foreignId('from_unit_id');
            $table->decimal('factor_to_base', 18, 6);
            $table->timestamps();
            $table->unique(['product_variant_id', 'from_unit_id']);
            $table->foreign(['merchant_id', 'product_variant_id'], 'conversions_variant_owner_fk')->references(['merchant_id', 'id'])->on('product_variants')->cascadeOnDelete();
            $table->foreign(['merchant_id', 'from_unit_id'], 'conversions_unit_owner_fk')->references(['merchant_id', 'id'])->on('unit_of_measures');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('unit_conversions');
    }
};
