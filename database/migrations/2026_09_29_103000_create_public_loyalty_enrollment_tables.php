<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_public_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id');
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs');
            $table->foreignId('loyalty_member_id')->constrained('loyalty_members');
            $table->foreignId('attributed_store_id')->nullable()->constrained('stores');
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->boolean('marketing_consent')->default(false);
            $table->timestamp('joined_at');
            $table->timestamps();
            $table->unique(['merchant_id', 'idempotency_key'], 'public_loyalty_join_idempotency_unique');
            $table->index(['loyalty_program_id', 'joined_at']);
        });

        Schema::create('public_loyalty_access_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id');
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs');
            $table->string('action', 20);
            $table->string('outcome', 30);
            $table->char('phone_hash', 64)->nullable();
            $table->char('ip_hash', 64);
            $table->string('user_agent_family', 80)->nullable();
            $table->timestamp('occurred_at');
            $table->index(['loyalty_program_id', 'action', 'occurred_at'], 'public_loyalty_abuse_window_idx');
            $table->index(['phone_hash', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_loyalty_access_events');
        Schema::dropIfExists('loyalty_public_enrollments');
    }
};
