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
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('merchant_subscription_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by_platform_operator_id')->constrained('platform_operators')->restrictOnDelete();
            $table->binary('idempotency_key', length: 16, fixed: true);
            $table->char('request_hash', 64);
            $table->string('reference', 120)->nullable();
            $table->decimal('amount', 14, 2);
            $table->char('currency', 3)->default('PKR');
            $table->date('paid_on');
            $table->timestamp('period_ends_before');
            $table->timestamp('period_ends_after');
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'idempotency_key']);
            $table->unique(['merchant_id', 'reference']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
