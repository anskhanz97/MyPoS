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
        Schema::create('extra_groups', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('product_id');
            $table->string('name', 120);
            $table->unsignedSmallInteger('minimum_selected')->default(0);
            $table->unsignedSmallInteger('maximum_selected')->default(1);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['merchant_id', 'id']);
            $table->foreign(['merchant_id', 'product_id'], 'extra_groups_product_owner_fk')->references(['merchant_id', 'id'])->on('products')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('extra_groups');
    }
};
