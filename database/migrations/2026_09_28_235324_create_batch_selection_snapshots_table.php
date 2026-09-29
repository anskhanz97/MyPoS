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
        Schema::create('batch_selection_snapshots', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('inventory_location_id');
            $table->foreignId('product_variant_id');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('strategy', 20);
            $table->decimal('requested_quantity_base', 19, 6);
            $table->date('business_date');
            $table->char('content_hash', 64);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->index(['merchant_id', 'store_id', 'expires_at'], 'batch_selection_snapshots_expiry_idx');
            $table->foreign(['merchant_id', 'store_id', 'inventory_location_id'], 'batch_selection_location_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('inventory_locations');
            $table->foreign(['merchant_id', 'product_variant_id'], 'batch_selection_variant_owner_fk')->references(['merchant_id', 'id'])->on('product_variants');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_selection_snapshots');
    }
};
