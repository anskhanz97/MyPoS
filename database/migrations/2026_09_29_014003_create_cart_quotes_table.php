<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_quotes', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('cart_id');
            $table->foreignId('quoted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quote_version');
            $table->unsignedInteger('cart_version');
            $table->string('pricing_version', 40);
            $table->char('content_hash', 64);
            $table->json('payload');
            $table->decimal('total', 19, 2);
            $table->timestamp('quoted_at');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['cart_id', 'quote_version']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->index(['cart_id', 'expires_at']);
            $table->foreign(['merchant_id', 'store_id', 'cart_id'])->references(['merchant_id', 'store_id', 'id'])->on('carts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_quotes');
    }
};
