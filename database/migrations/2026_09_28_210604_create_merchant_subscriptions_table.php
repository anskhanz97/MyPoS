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
        Schema::create('merchant_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('status', 20);
            $table->timestamp('starts_at');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_ends_at');
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('restricted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();
            $table->index(['status', 'current_period_ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchant_subscriptions');
    }
};
