<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_line_extras', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('cart_line_id');
            $table->foreignId('extra_option_id');
            $table->string('group_name_snapshot', 120);
            $table->string('option_name_snapshot', 120);
            $table->decimal('unit_price_delta', 19, 2);
            $table->unsignedInteger('sequence');
            $table->timestamps();
            $table->unique(['cart_line_id', 'extra_option_id']);
            $table->foreign(['merchant_id', 'store_id', 'cart_line_id'], 'cart_extras_line_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('cart_lines')->cascadeOnDelete();
            $table->foreign(['merchant_id', 'extra_option_id'])->references(['merchant_id', 'id'])->on('extra_options');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_line_extras');
    }
};
