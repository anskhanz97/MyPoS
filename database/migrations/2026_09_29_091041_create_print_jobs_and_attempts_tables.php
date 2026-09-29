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
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('register_id');
            $table->foreignId('receipt_snapshot_id');
            $table->foreignId('requested_by_user_id');
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->string('copy_type', 20);
            $table->unsignedTinyInteger('original_guard')->nullable();
            $table->string('state', 30)->default('created');
            $table->string('printer_name', 160)->nullable();
            $table->unsignedSmallInteger('helper_protocol')->default(1);
            $table->json('render_payload');
            $table->json('signed_authorization');
            $table->string('reason', 500)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'register_id', 'idempotency_key'], 'print_jobs_register_idempotency_uq');
            $table->unique(['receipt_snapshot_id', 'original_guard'], 'print_jobs_one_original_uq');
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->index(['register_id', 'state', 'created_at']);
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'store_id', 'register_id'], 'print_jobs_register_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('registers');
            $table->foreign(['merchant_id', 'store_id', 'receipt_snapshot_id'], 'print_jobs_receipt_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('receipt_snapshots');
            $table->foreign('requested_by_user_id')->references('id')->on('users');
        });
        Schema::create('print_attempts', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('print_job_id');
            $table->uuid('helper_event_id')->unique();
            $table->char('payload_hash', 64);
            $table->unsignedInteger('attempt_number');
            $table->string('outcome', 30);
            $table->string('helper_id', 160);
            $table->string('helper_version', 40);
            $table->string('printer_name', 160);
            $table->string('error_code', 80)->nullable();
            $table->string('safe_message', 500)->nullable();
            $table->timestamp('reported_at');
            $table->timestamp('created_at');
            $table->unique(['print_job_id', 'attempt_number']);
            $table->foreign('print_job_id')->references('id')->on('print_jobs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('print_attempts');
        Schema::dropIfExists('print_jobs');
    }
};
