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
        Schema::create('staff_device_sessions', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id');
            $table->foreignId('device_enrollment_id');
            $table->foreignId('merchant_membership_id');
            $table->string('session_token_hash', 64)->unique();
            $table->timestamp('started_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'id']);
            $table->index(['device_enrollment_id', 'revoked_at', 'expires_at'], 'staff_device_sessions_active_idx');
            $table->foreign(['merchant_id', 'store_id'], 'staff_device_sessions_store_owner_fk')->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'device_enrollment_id'], 'staff_device_sessions_device_owner_fk')->references(['merchant_id', 'id'])->on('device_enrollments');
            $table->foreign(['merchant_id', 'merchant_membership_id'], 'staff_device_sessions_membership_owner_fk')->references(['merchant_id', 'id'])->on('merchant_memberships');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_device_sessions');
    }
};
