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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('inventory_location_id');
            $table->foreignId('product_variant_id');
            $table->foreignId('inventory_adjustment_reason_id')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reverses_stock_movement_id')->nullable();
            $table->string('movement_kind', 32);
            $table->decimal('quantity_base', 19, 6);
            $table->decimal('unit_cost', 19, 6)->nullable();
            $table->string('cost_state', 20)->default('pending');
            $table->string('source_type', 80);
            $table->string('source_reference', 120);
            $table->string('source_line_reference', 120)->default('main');
            $table->string('source_effect', 80)->default('stock');
            $table->char('source_key', 64);
            $table->char('payload_hash', 64);
            $table->string('reason_text', 500)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->unique(['merchant_id', 'store_id', 'source_key'], 'stock_movements_source_unique');
            $table->unique('reverses_stock_movement_id');
            $table->index(['merchant_id', 'store_id', 'inventory_location_id', 'product_variant_id', 'occurred_at'], 'stock_movements_balance_history_idx');
            $table->index(['merchant_id', 'store_id', 'source_type', 'source_reference'], 'stock_movements_source_lookup_idx');
            $table->foreign(['merchant_id', 'store_id', 'inventory_location_id'], 'stock_movements_location_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('inventory_locations');
            $table->foreign(['merchant_id', 'product_variant_id'], 'stock_movements_variant_owner_fk')->references(['merchant_id', 'id'])->on('product_variants');
            $table->foreign(['merchant_id', 'inventory_adjustment_reason_id'], 'stock_movements_reason_owner_fk')->references(['merchant_id', 'id'])->on('inventory_adjustment_reasons');
            $table->foreign(['merchant_id', 'store_id', 'reverses_stock_movement_id'], 'stock_movements_reversal_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('stock_movements');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
