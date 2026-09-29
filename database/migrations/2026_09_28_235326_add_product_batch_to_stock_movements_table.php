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
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('product_batch_id')->nullable()->after('product_variant_id');
            $table->index(['merchant_id', 'store_id', 'product_batch_id', 'occurred_at'], 'stock_movements_batch_history_idx');
            $table->foreign(['merchant_id', 'product_variant_id', 'product_batch_id'], 'stock_movements_batch_owner_fk')->references(['merchant_id', 'product_variant_id', 'id'])->on('product_batches');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign('stock_movements_batch_owner_fk');
            $table->dropIndex('stock_movements_batch_history_idx');
            $table->dropColumn('product_batch_id');
        });
    }
};
