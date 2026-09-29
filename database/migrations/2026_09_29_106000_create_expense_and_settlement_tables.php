<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $t): void {
            $t->id();
            $t->binary('public_id', 16, true)->unique();
            $t->foreignId('merchant_id');
            $t->string('code', 40);
            $t->string('name', 120);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['merchant_id', 'code']);
        });
        Schema::create('expenses', function (Blueprint $t): void {
            $t->id();
            $t->binary('public_id', 16, true)->unique();
            $t->foreignId('merchant_id');
            $t->foreignId('store_id');
            $t->foreignId('expense_category_id')->constrained('expense_categories');
            $t->foreignId('cash_session_id')->nullable()->constrained('cash_sessions');
            $t->foreignId('correction_of_id')->nullable()->constrained('expenses');
            $t->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->uuid('idempotency_key');
            $t->char('payload_hash', 64);
            $t->string('state', 20);
            $t->date('expense_date');
            $t->string('payee', 160);
            $t->decimal('amount', 19, 2);
            $t->string('payment_source', 30);
            $t->string('reference', 160)->nullable();
            $t->string('reason', 500);
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['merchant_id', 'idempotency_key']);
            $t->index(['store_id', 'expense_date']);
        });
        Schema::create('expense_attachments', function (Blueprint $t): void {
            $t->id();
            $t->binary('public_id', 16, true)->unique();
            $t->foreignId('merchant_id');
            $t->foreignId('expense_id')->constrained('expenses');
            $t->string('disk', 40);
            $t->string('path', 500);
            $t->string('original_name', 255);
            $t->string('mime_type', 100);
            $t->unsignedBigInteger('size');
            $t->char('sha256', 64);
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('settlement_batches', function (Blueprint $t): void {
            $t->id();
            $t->binary('public_id', 16, true)->unique();
            $t->foreignId('merchant_id');
            $t->foreignId('store_id');
            $t->string('provider', 120);
            $t->string('statement_reference', 160);
            $t->date('period_start');
            $t->date('period_end');
            $t->string('provenance', 30)->default('user_entered');
            $t->string('state', 20)->default('open');
            $t->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['merchant_id', 'provider', 'statement_reference'], 'settlement_batch_reference_unique');
        });
        Schema::create('settlement_lines', function (Blueprint $t): void {
            $t->id();
            $t->binary('public_id', 16, true)->unique();
            $t->foreignId('merchant_id');
            $t->foreignId('settlement_batch_id')->constrained('settlement_batches');
            $t->foreignId('sale_tender_id')->nullable()->constrained('sale_tenders');
            $t->string('external_reference', 160);
            $t->decimal('expected_amount', 19, 2);
            $t->decimal('settled_amount', 19, 2);
            $t->decimal('fee_amount', 19, 2);
            $t->decimal('difference_amount', 19, 2);
            $t->string('state', 20);
            $t->string('exception_note', 500)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['settlement_batch_id', 'external_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settlement_lines');
        Schema::dropIfExists('settlement_batches');
        Schema::dropIfExists('expense_attachments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
