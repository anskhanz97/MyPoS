<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_email_settings', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('merchant_id')->unique();
            $t->boolean('enabled')->default(false);
            $t->string('provider_label', 80)->default('configured mail transport');
            $t->string('from_address');
            $t->string('from_name');
            $t->timestamps();
        });
        Schema::create('notification_preferences', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('merchant_id');
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->boolean('in_app_enabled')->default(true);
            $t->boolean('operational_email_enabled')->default(false);
            $t->timestamps();
            $t->unique(['merchant_id', 'user_id']);
        });
        Schema::create('notification_deliveries', function (Blueprint $t): void {
            $t->id();
            $t->binary('public_id', 16, true)->unique();
            $t->foreignId('merchant_id');
            $t->foreignId('store_id')->nullable();
            $t->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('channel', 30);
            $t->string('template_key', 80);
            $t->string('source_type', 80);
            $t->string('source_reference', 120);
            $t->string('recipient', 320);
            $t->char('deduplication_key', 64);
            $t->string('state', 30)->default('queued');
            $t->unsignedSmallInteger('attempt_count')->default(0);
            $t->string('provider_message_id')->nullable();
            $t->string('failure_code', 80)->nullable();
            $t->string('safe_failure_message', 500)->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();
            $t->unique(['merchant_id', 'channel', 'deduplication_key'], 'notification_delivery_dedup_unique');
        });
        Schema::create('notification_attempts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('notification_delivery_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('attempt_number');
            $t->string('outcome', 30);
            $t->string('provider_message_id')->nullable();
            $t->string('failure_code', 80)->nullable();
            $t->string('safe_message', 500)->nullable();
            $t->timestamp('attempted_at')->useCurrent();
            $t->unique(['notification_delivery_id', 'attempt_number'], 'notification_attempt_number_unique');
        });
        Schema::create('in_app_alerts', function (Blueprint $t): void {
            $t->id();
            $t->binary('public_id', 16, true)->unique();
            $t->foreignId('merchant_id');
            $t->foreignId('store_id')->nullable();
            $t->string('audience_role', 30)->default('manager');
            $t->string('category', 60);
            $t->string('severity', 20);
            $t->string('title', 160);
            $t->string('message', 500);
            $t->string('source_type', 80);
            $t->string('source_reference', 120);
            $t->char('deduplication_key', 64);
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->unique(['merchant_id', 'deduplication_key']);
            $t->index(['merchant_id', 'store_id', 'resolved_at']);
        });
        Schema::create('in_app_alert_reads', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('in_app_alert_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('read_at');
            $t->unique(['in_app_alert_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('in_app_alert_reads');
        Schema::dropIfExists('in_app_alerts');
        Schema::dropIfExists('notification_attempts');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('merchant_email_settings');
    }
};
