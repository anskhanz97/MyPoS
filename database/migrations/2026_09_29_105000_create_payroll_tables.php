<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_pay_terms', function (Blueprint $table): void {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('staff_profile_id')->constrained('staff_profiles');
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('pay_basis', 20);
            $table->decimal('base_rate', 19, 2);
            $table->decimal('standard_monthly_hours', 8, 2)->default(173.33);
            $table->decimal('overtime_multiplier', 8, 4)->default(1.5000);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['staff_profile_id', 'effective_from']);
        });
        Schema::create('employee_advances', function (Blueprint $table): void {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('staff_profile_id')->constrained('staff_profiles');
            $table->decimal('original_amount', 19, 2);
            $table->decimal('outstanding_amount', 19, 2);
            $table->string('reason', 500);
            $table->date('issued_on');
            $table->string('state', 20)->default('open');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('payroll_runs', function (Blueprint $table): void {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('state', 20)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'period_start', 'period_end']);
        });
        Schema::create('payroll_items', function (Blueprint $table): void {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('payroll_run_id')->constrained('payroll_runs');
            $table->foreignId('staff_profile_id')->constrained('staff_profiles');
            $table->foreignId('payroll_input_snapshot_id')->constrained('payroll_input_snapshots');
            $table->string('state', 20)->default('draft');
            $table->decimal('base_amount', 19, 2);
            $table->decimal('overtime_amount', 19, 2);
            $table->decimal('allowance_amount', 19, 2);
            $table->decimal('deduction_amount', 19, 2);
            $table->decimal('statutory_amount', 19, 2);
            $table->decimal('advance_amount', 19, 2);
            $table->decimal('gross_amount', 19, 2);
            $table->decimal('net_amount', 19, 2);
            $table->decimal('paid_amount', 19, 2)->default(0);
            $table->json('calculation');
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['payroll_run_id', 'staff_profile_id']);
        });
        Schema::create('payroll_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('payroll_item_id')->constrained('payroll_items');
            $table->string('kind', 30);
            $table->decimal('amount', 19, 2);
            $table->string('provenance', 20);
            $table->string('label', 160);
            $table->string('reason', 500)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('advance_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id');
            $table->foreignId('employee_advance_id')->constrained('employee_advances');
            $table->foreignId('payroll_item_id')->constrained('payroll_items');
            $table->decimal('amount', 19, 2);
            $table->timestamp('allocated_at');
            $table->unique(['employee_advance_id', 'payroll_item_id']);
        });
        Schema::create('payslip_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('payroll_item_id')->unique()->constrained('payroll_items');
            $table->json('payload');
            $table->char('content_hash', 64);
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('payroll_payouts', function (Blueprint $table): void {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('payroll_item_id')->constrained('payroll_items');
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->decimal('amount', 19, 2);
            $table->string('payment_method', 20);
            $table->string('reference', 160)->nullable();
            $table->timestamp('paid_at');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['merchant_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payouts');
        Schema::dropIfExists('payslip_snapshots');
        Schema::dropIfExists('advance_allocations');
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('employee_advances');
        Schema::dropIfExists('staff_pay_terms');
    }
};
