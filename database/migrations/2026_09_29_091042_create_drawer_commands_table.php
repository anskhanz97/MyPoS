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
        Schema::create('drawer_commands', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('register_id');
            $table->foreignId('cash_session_id');
            $table->foreignId('requested_by_user_id');
            $table->foreignId('print_job_id')->nullable()->unique();
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->string('kind', 30);
            $table->string('reason', 500);
            $table->string('state', 30)->default('authorized');
            $table->uuid('helper_event_id')->nullable()->unique();
            $table->char('outcome_payload_hash', 64)->nullable();
            $table->json('signed_authorization');
            $table->timestamp('expires_at');
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'register_id', 'idempotency_key'], 'drawer_commands_register_idempotency_uq');
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'store_id', 'register_id'], 'drawer_commands_register_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('registers');
            $table->foreign(['merchant_id', 'store_id', 'cash_session_id'], 'drawer_commands_session_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('cash_sessions');
            $table->foreign('requested_by_user_id')->references('id')->on('users');
            $table->foreign('print_job_id')->references('id')->on('print_jobs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drawer_commands');
    }
};
