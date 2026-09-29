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
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('source_store_id');
            $table->foreignId('destination_store_id');
            $table->foreignId('source_inventory_location_id');
            $table->foreignId('destination_inventory_location_id');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 40);
            $table->string('state', 24)->default('draft');
            $table->unsignedBigInteger('version')->default(1);
            $table->string('notes', 500)->nullable();
            $table->uuid('dispatch_key')->nullable();
            $table->char('dispatch_hash', 64)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'number']);
            $table->unique(['merchant_id', 'id']);
            $table->unique(['merchant_id', 'dispatch_key']);
            $table->index(['merchant_id', 'source_store_id', 'state', 'created_at'], 'stock_transfers_source_state_idx');
            $table->index(['merchant_id', 'destination_store_id', 'state', 'created_at'], 'stock_transfers_destination_state_idx');
            $table->foreign(['merchant_id', 'source_store_id'], 'stock_transfers_source_store_fk')->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'destination_store_id'], 'stock_transfers_destination_store_fk')->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'source_store_id', 'source_inventory_location_id'], 'stock_transfers_source_location_fk')->references(['merchant_id', 'store_id', 'id'])->on('inventory_locations');
            $table->foreign(['merchant_id', 'destination_store_id', 'destination_inventory_location_id'], 'stock_transfers_destination_location_fk')->references(['merchant_id', 'store_id', 'id'])->on('inventory_locations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
