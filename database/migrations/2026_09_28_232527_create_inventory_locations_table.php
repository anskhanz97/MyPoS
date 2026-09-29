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
        Schema::create('inventory_locations', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('disposition', 24)->default('sellable');
            $table->boolean('is_default')->default(false);
            $table->unsignedTinyInteger('default_slot')->nullable()->storedAs('case when `is_default` = 1 then 1 else null end');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->unique(['merchant_id', 'store_id', 'code']);
            $table->unique(['merchant_id', 'store_id', 'default_slot'], 'inventory_locations_one_default');
            $table->foreign(['merchant_id', 'store_id'], 'inventory_locations_store_owner_fk')->references(['merchant_id', 'id'])->on('stores')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_locations');
    }
};
