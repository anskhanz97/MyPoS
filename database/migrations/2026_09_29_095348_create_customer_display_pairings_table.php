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
        Schema::create('customer_display_pairings', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('register_id');
            $table->foreignId('created_by_user_id');
            $table->char('code_hash', 64)->unique();
            $table->char('display_token_hash', 64)->nullable()->unique();
            $table->uuid('channel_id')->unique();
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('active_guard')->nullable();
            $table->timestamp('pairing_expires_at');
            $table->timestamp('session_expires_at')->nullable();
            $table->timestamp('paired_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['register_id', 'active_guard'], 'customer_display_one_active_uq');
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'store_id', 'register_id'], 'customer_display_register_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('registers');
            $table->foreign('created_by_user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_display_pairings');
    }
};
