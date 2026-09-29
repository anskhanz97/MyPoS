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
        Schema::create('security_audit_events', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_id')->nullable();
            $table->foreignId('device_enrollment_id')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_membership_id')->nullable();
            $table->string('event_code', 80);
            $table->string('outcome', 20);
            $table->binary('request_id', length: 16, fixed: true)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['merchant_id', 'occurred_at']);
            $table->index(['event_code', 'outcome', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('security_audit_events');
    }
};
