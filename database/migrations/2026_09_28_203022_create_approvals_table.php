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
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id');
            $table->foreignId('device_enrollment_id');
            $table->foreignId('requester_membership_id');
            $table->foreignId('approver_membership_id');
            $table->string('permission_code', 80);
            $table->string('target_type', 80);
            $table->binary('target_public_id', length: 16, fixed: true);
            $table->string('reason', 500);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['merchant_id', 'permission_code', 'expires_at']);
            $table->foreign(['merchant_id', 'store_id'], 'approvals_store_owner_fk')->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'device_enrollment_id'], 'approvals_device_owner_fk')->references(['merchant_id', 'id'])->on('device_enrollments');
            $table->foreign(['merchant_id', 'requester_membership_id'], 'approvals_requester_owner_fk')->references(['merchant_id', 'id'])->on('merchant_memberships');
            $table->foreign(['merchant_id', 'approver_membership_id'], 'approvals_approver_owner_fk')->references(['merchant_id', 'id'])->on('merchant_memberships');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
