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
        Schema::create('device_pairing_codes', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id');
            $table->foreignId('register_id');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->boolean('replace_existing')->default(false);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->foreign(['merchant_id', 'store_id'], 'pairing_codes_store_owner_fk')->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'store_id', 'register_id'], 'pairing_codes_register_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('registers');
            $table->index(['merchant_id', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_pairing_codes');
    }
};
