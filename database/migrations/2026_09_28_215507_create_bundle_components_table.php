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
        Schema::create('bundle_components', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('bundle_variant_id');
            $table->foreignId('component_variant_id');
            $table->decimal('quantity_base', 18, 6);
            $table->timestamps();
            $table->unique(['bundle_variant_id', 'component_variant_id']);
            $table->foreign(['merchant_id', 'bundle_variant_id'], 'bundle_parent_owner_fk')->references(['merchant_id', 'id'])->on('product_variants')->cascadeOnDelete();
            $table->foreign(['merchant_id', 'component_variant_id'], 'bundle_component_owner_fk')->references(['merchant_id', 'id'])->on('product_variants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bundle_components');
    }
};
