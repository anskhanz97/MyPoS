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
        Schema::create('stocktakes', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('inventory_location_id');
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 40);
            $table->string('state', 20)->default('counting');
            $table->unsignedBigInteger('version')->default(1);
            $table->unsignedBigInteger('baseline_movement_id')->default(0);
            $table->uuid('post_key')->nullable();
            $table->char('post_hash', 64)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'number']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->unique(['merchant_id', 'store_id', 'post_key']);
            $table->index(['merchant_id', 'store_id', 'state', 'opened_at'], 'stocktakes_store_state_idx');
            $table->foreign(['merchant_id', 'store_id'], 'stocktakes_store_owner_fk')->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'store_id', 'inventory_location_id'], 'stocktakes_location_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('inventory_locations');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocktakes');
    }
};
