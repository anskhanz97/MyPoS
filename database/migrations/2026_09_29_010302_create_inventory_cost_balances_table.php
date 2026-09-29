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
        Schema::create('inventory_cost_balances', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('product_variant_id');
            $table->decimal('quantity_base', 19, 6)->default(0);
            $table->decimal('inventory_value', 25, 6)->nullable();
            $table->decimal('average_unit_cost', 19, 6)->nullable();
            $table->string('cost_state', 20)->default('pending');
            $table->decimal('pending_negative_quantity_base', 19, 6)->default(0);
            $table->foreignId('last_cost_entry_id')->nullable();
            $table->unsignedBigInteger('projection_version')->default(0);
            $table->timestamps();

            $table->unique(['merchant_id', 'store_id', 'product_variant_id'], 'cost_balances_dimension_unique');
            $table->unique(['merchant_id', 'store_id', 'id'], 'cost_balances_owner_unique');
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'product_variant_id'])->references(['merchant_id', 'id'])->on('product_variants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_balances');
    }
};
