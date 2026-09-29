<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('register_id');
            $table->foreignId('opened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('current_custodian_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 40);
            $table->string('status', 30);
            $table->unsignedTinyInteger('active_guard')->nullable()->default(1);
            $table->uuid('open_idempotency_key');
            $table->char('open_payload_hash', 64);
            $table->string('currency', 3)->default('PKR');
            $table->date('business_date');
            $table->decimal('opening_amount', 19, 2);
            $table->decimal('expected_amount', 19, 2)->nullable();
            $table->decimal('counted_amount', 19, 2)->nullable();
            $table->decimal('variance_amount', 19, 2)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('handover_count')->default(0);
            $table->unsignedInteger('pending_server_sale_count')->default(0);
            $table->unsignedInteger('pending_offline_sale_count')->default(0);
            $table->timestamp('opened_at');
            $table->timestamp('close_cutoff_at')->nullable();
            $table->timestamp('count_submitted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'number']);
            $table->unique(['merchant_id', 'store_id', 'open_idempotency_key']);
            $table->unique(['register_id', 'active_guard'], 'cash_sessions_one_active_register_uq');
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->index(['store_id', 'status', 'opened_at']);
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'store_id', 'register_id'])->references(['merchant_id', 'store_id', 'id'])->on('registers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
