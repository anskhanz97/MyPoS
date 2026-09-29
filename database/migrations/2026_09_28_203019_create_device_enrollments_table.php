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
        Schema::create('device_enrollments', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id');
            $table->foreignId('register_id');
            $table->string('name', 120);
            $table->string('device_key_hash');
            $table->string('status', 20)->default('active');
            $table->timestamp('enrolled_at');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'id']);
            $table->foreign(['merchant_id', 'store_id'], 'device_enrollments_store_owner_fk')->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'store_id', 'register_id'], 'device_enrollments_register_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('registers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_enrollments');
    }
};
