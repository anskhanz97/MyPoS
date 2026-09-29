<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_commands', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('register_id');
            $table->foreignId('device_enrollment_id');
            $table->foreignId('cash_session_id')->nullable();
            $table->uuid('command_id');
            $table->string('command_type', 40);
            $table->unsignedSmallInteger('schema_version');
            $table->timestamp('committed_at_local');
            $table->string('snapshot_version', 160);
            $table->unsignedBigInteger('fencing_epoch');
            $table->char('payload_hash', 64);
            $table->json('payload');
            $table->string('state', 30)->default('pending');
            $table->unsignedInteger('attempt_count')->default(1);
            $table->string('resource_type', 80)->nullable();
            $table->binary('resource_public_id', 16, true)->nullable();
            $table->string('conflict_code', 80)->nullable();
            $table->string('safe_message', 500)->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'command_id']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->index(['device_enrollment_id', 'state', 'committed_at_local'], 'offline_commands_device_state_idx');
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'store_id', 'register_id'], 'offline_commands_register_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('registers');
            $table->foreign(['merchant_id', 'store_id', 'device_enrollment_id'], 'offline_commands_device_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('device_enrollments');
            $table->foreign(['merchant_id', 'store_id', 'cash_session_id'], 'offline_commands_session_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('cash_sessions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_commands');
    }
};
