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
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promotion_rule_id')->constrained()->restrictOnDelete();
            $table->uuid('client_transaction_id');
            $table->string('customer_reference', 120)->nullable();
            $table->string('status', 20)->default('finalized');
            $table->decimal('discount_amount', 19, 2);
            $table->timestamp('finalized_at');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'promotion_rule_id', 'client_transaction_id'], 'coupon_usage_idempotency_unique');
            $table->index(['promotion_rule_id', 'status']);
            $table->foreign(['merchant_id', 'promotion_rule_id'], 'coupon_usages_rule_owner_fk')->references(['merchant_id', 'id'])->on('promotion_rules');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
