<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('default_store_id')->nullable()->constrained('stores');
            $table->string('employee_number', 40);
            $table->string('display_name', 160);
            $table->string('employment_status', 20)->default('active');
            $table->date('hire_date');
            $table->date('end_date')->nullable();
            $table->string('pay_basis', 20);
            $table->decimal('base_pay', 19, 2);
            $table->boolean('overtime_eligible')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['merchant_id', 'user_id']);
            $table->unique(['merchant_id', 'employee_number']);
            $table->unique(['merchant_id', 'id']);
        });
        Schema::create('workforce_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->unique()->constrained('merchants');
            $table->unsignedSmallInteger('grace_minutes')->default(5);
            $table->unsignedSmallInteger('max_shift_minutes')->default(960);
            $table->unsignedSmallInteger('break_required_after_minutes')->default(300);
            $table->unsignedSmallInteger('overtime_after_minutes')->default(480);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });
        Schema::create('rota_shifts', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('staff_profile_id')->constrained('staff_profiles');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state', 20)->default('planned');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->string('notes', 500)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->index(['staff_profile_id', 'starts_at', 'ends_at']);
        });
        Schema::create('attendance_shifts', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('staff_profile_id')->constrained('staff_profiles');
            $table->foreignId('rota_shift_id')->nullable()->constrained('rota_shifts');
            $table->string('state', 24);
            $table->timestamp('clocked_in_at');
            $table->timestamp('clocked_out_at')->nullable();
            $table->unsignedInteger('break_seconds')->default(0);
            $table->timestamp('open_break_at')->nullable();
            $table->timestamps();
            $table->index(['staff_profile_id', 'state']);
            $table->index(['store_id', 'clocked_in_at']);
        });
        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('staff_profile_id')->constrained('staff_profiles');
            $table->foreignId('attendance_shift_id')->nullable()->constrained('attendance_shifts');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->string('event_type', 30);
            $table->timestamp('occurred_at');
            $table->string('reason', 500)->nullable();
            $table->json('before_values')->nullable();
            $table->json('after_values')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['merchant_id', 'idempotency_key']);
            $table->index(['staff_profile_id', 'occurred_at']);
        });
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->string('code', 40);
            $table->string('name', 120);
            $table->boolean('is_paid')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['merchant_id', 'code']);
        });
        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id');
            $table->foreignId('staff_profile_id')->constrained('staff_profiles');
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->unsignedSmallInteger('year');
            $table->decimal('allocated_days', 8, 2)->default(0);
            $table->decimal('used_days', 8, 2)->default(0);
            $table->decimal('reserved_days', 8, 2)->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['staff_profile_id', 'leave_type_id', 'year']);
        });
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('staff_profile_id')->constrained('staff_profiles');
            $table->foreignId('leave_type_id')->constrained('leave_types');
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state', 20)->default('pending');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('days', 8, 2);
            $table->string('staff_note', 500)->nullable();
            $table->string('decision_reason', 500)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['staff_profile_id', 'starts_on', 'ends_on']);
        });
        Schema::create('payroll_input_snapshots', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('staff_profile_id')->constrained('staff_profiles');
            $table->foreignId('frozen_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->char('content_hash', 64);
            $table->json('payload');
            $table->timestamp('frozen_at');
            $table->timestamps();
            $table->unique(['staff_profile_id', 'period_start', 'period_end'], 'payroll_input_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_input_snapshots');
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
        Schema::dropIfExists('attendance_events');
        Schema::dropIfExists('attendance_shifts');
        Schema::dropIfExists('rota_shifts');
        Schema::dropIfExists('workforce_policies');
        Schema::dropIfExists('staff_profiles');
    }
};
