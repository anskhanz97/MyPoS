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
        Schema::create('merchant_integrations', function (Blueprint $table): void {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->string('provider', 40);
            $table->string('category', 30);
            $table->string('mode', 20)->default('sandbox');
            $table->string('state', 20)->default('draft');
            $table->json('config')->nullable();
            $table->text('credentials')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('last_test_state', 20)->nullable();
            $table->string('last_test_message', 240)->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'provider']);
            $table->index(['merchant_id', 'category', 'state']);
        });

        Schema::create('notification_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id');
            $table->foreignId('operational_customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('normalized_phone', 20);
            $table->string('channel', 20);
            $table->string('purpose', 20)->default('transactional');
            $table->string('state', 20);
            $table->string('source', 40);
            $table->string('evidence_reference', 160)->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
            $table->unique(['merchant_id', 'normalized_phone', 'channel', 'purpose'], 'notification_consent_identity_unique');
        });

        Schema::create('integration_callbacks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notification_delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider_event_id', 160);
            $table->string('event_type', 80);
            $table->string('outcome', 30);
            $table->char('payload_hash', 64);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_integration_id', 'provider_event_id'], 'integration_callback_event_unique');
        });

        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->foreignId('merchant_integration_id')->nullable()->after('requested_by_user_id')->constrained()->nullOnDelete();
            $table->string('message_class', 30)->default('transactional')->after('channel');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('read_at')->nullable()->after('delivered_at');
            $table->timestamp('failed_at')->nullable()->after('read_at');
            $table->unsignedSmallInteger('segment_count')->nullable()->after('failed_at');
            $table->unsignedBigInteger('provider_cost_minor')->nullable()->after('segment_count');
            $table->char('provider_cost_currency', 3)->nullable()->after('provider_cost_minor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification_deliveries', function (Blueprint $table): void {
            $table->dropForeign(['merchant_integration_id']);
            $table->dropColumn(['merchant_integration_id', 'message_class', 'delivered_at', 'read_at', 'failed_at', 'segment_count', 'provider_cost_minor', 'provider_cost_currency']);
        });
        Schema::dropIfExists('integration_callbacks');
        Schema::dropIfExists('notification_consents');
        Schema::dropIfExists('merchant_integrations');
    }
};
