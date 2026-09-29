<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('register_id');
            $table->foreignId('inventory_location_id');
            $table->foreignId('customer_id')->nullable()->constrained('operational_customers')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference', 40);
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->string('pricing_version', 40)->nullable();
            $table->json('coupon_codes')->nullable();
            $table->decimal('manual_cart_discount', 19, 2)->default(0);
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('resumed_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'reference']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->index(['store_id', 'register_id', 'status', 'updated_at']);
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'store_id', 'register_id'])->references(['merchant_id', 'store_id', 'id'])->on('registers');
            $table->foreign(['merchant_id', 'store_id', 'inventory_location_id'], 'carts_location_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('inventory_locations');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
