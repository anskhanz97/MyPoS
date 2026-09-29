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
        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id')->nullable();
            $table->string('event_type', 100);
            $table->string('aggregate_type', 80);
            $table->string('aggregate_id', 36);
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('available_at');
            $table->timestamp('published_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamps();

            $table->index(['published_at', 'available_at', 'id'], 'outbox_dispatch_idx');
            $table->foreign('merchant_id')->references('id')->on('merchants');
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
    }
};
