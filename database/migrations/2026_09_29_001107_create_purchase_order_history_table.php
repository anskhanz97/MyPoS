<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_histories', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('purchase_order_id');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->string('from_state', 24)->nullable();
            $table->string('to_state', 24);
            $table->unsignedBigInteger('version');
            $table->string('reason', 500)->nullable();
            $table->json('snapshot');
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['merchant_id', 'store_id', 'purchase_order_id', 'version'], 'po_history_version_unique');
            $table->foreign(['merchant_id', 'store_id', 'purchase_order_id'], 'purchase_order_history_order_fk')->references(['merchant_id', 'store_id', 'id'])->on('purchase_orders')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_histories');
    }
};
