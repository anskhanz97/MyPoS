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
        Schema::create('inventory_cost_entries', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('product_variant_id');
            $table->foreignId('inventory_cost_balance_id');
            $table->foreignId('stock_movement_id')->nullable();
            $table->foreignId('adjusts_cost_entry_id')->nullable();
            $table->string('entry_kind', 40);
            $table->decimal('quantity_delta', 19, 6);
            $table->decimal('unit_cost', 19, 6)->nullable();
            $table->decimal('value_delta', 25, 6)->nullable();
            $table->decimal('previous_quantity', 19, 6);
            $table->decimal('previous_value', 25, 6)->nullable();
            $table->decimal('previous_average', 19, 6)->nullable();
            $table->decimal('resulting_quantity', 19, 6);
            $table->decimal('resulting_value', 25, 6)->nullable();
            $table->decimal('resulting_average', 19, 6)->nullable();
            $table->string('cost_state', 20);
            $table->decimal('resolved_negative_quantity', 19, 6)->default(0);
            $table->decimal('cost_adjustment_value', 25, 6)->default(0);
            $table->string('valuation_timing', 32)->default('current_sequence');
            $table->string('source_type', 80);
            $table->string('source_reference', 120);
            $table->string('source_line_reference', 120)->default('main');
            $table->char('source_key', 64);
            $table->char('payload_hash', 64);
            $table->timestamp('effective_at');
            $table->timestamp('applied_at');
            $table->timestamps();

            $table->unique(['merchant_id', 'store_id', 'source_key'], 'cost_entries_source_unique');
            $table->index(['merchant_id', 'store_id', 'product_variant_id', 'id'], 'cost_entries_product_sequence_idx');
            $table->foreign(['merchant_id', 'store_id', 'inventory_cost_balance_id'], 'cost_entries_balance_fk')->references(['merchant_id', 'store_id', 'id'])->on('inventory_cost_balances');
            $table->foreign(['merchant_id', 'product_variant_id'], 'cost_entries_variant_fk')->references(['merchant_id', 'id'])->on('product_variants');
            $table->foreign('stock_movement_id')->references('id')->on('stock_movements');
            $table->foreign('adjusts_cost_entry_id')->references('id')->on('inventory_cost_entries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_cost_entries');
    }
};
