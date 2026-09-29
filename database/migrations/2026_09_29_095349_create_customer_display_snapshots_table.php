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
        Schema::create('customer_display_snapshots', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('register_id');
            $table->foreignId('customer_display_pairing_id')->unique();
            $table->foreignId('cart_id')->nullable();
            $table->foreignId('sale_id')->nullable();
            $table->unsignedBigInteger('sequence')->default(0);
            $table->string('state', 20)->default('idle');
            $table->json('payload');
            $table->timestamp('clear_at')->nullable();
            $table->timestamp('published_at');
            $table->timestamps();
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->foreign(['merchant_id', 'store_id', 'register_id'], 'customer_display_snapshot_register_fk')->references(['merchant_id', 'store_id', 'id'])->on('registers');
            $table->foreign(['merchant_id', 'store_id', 'customer_display_pairing_id'], 'customer_display_snapshot_pairing_fk')->references(['merchant_id', 'store_id', 'id'])->on('customer_display_pairings');
            $table->foreign(['merchant_id', 'store_id', 'cart_id'], 'customer_display_snapshot_cart_fk')->references(['merchant_id', 'store_id', 'id'])->on('carts');
            $table->foreign(['merchant_id', 'store_id', 'sale_id'], 'customer_display_snapshot_sale_fk')->references(['merchant_id', 'store_id', 'id'])->on('sales');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_display_snapshots');
    }
};
