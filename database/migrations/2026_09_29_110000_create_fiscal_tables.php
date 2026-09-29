<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_configurations', function (Blueprint $t): void {
            $t->id();
            $t->binary('public_id', 16, true)->unique();
            $t->foreignId('merchant_id');
            $t->foreignId('store_id');
            $t->string('authority', 30);
            $t->string('adapter_version', 40);
            $t->enum('environment', ['sandbox', 'production']);
            $t->enum('applicability', ['not_required', 'required', 'pending_verification']);
            $t->string('state', 30)->default('draft');
            $t->string('tax_registration_number', 80)->nullable();
            $t->string('outlet_reference', 120)->nullable();
            $t->string('outage_policy', 40)->default('block_sale');
            $t->json('profile')->nullable();
            $t->text('credentials')->nullable();
            $t->char('evidence_hash', 64)->nullable();
            $t->timestamp('sandbox_verified_at')->nullable();
            $t->timestamp('production_approved_at')->nullable();
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['merchant_id', 'store_id', 'authority'], 'fiscal_config_store_authority_unique');
        });
        Schema::create('fiscal_product_mappings', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('fiscal_configuration_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained('product_variants');
            $t->string('hs_code', 30);
            $t->string('uom_code', 80);
            $t->string('rate_label', 80);
            $t->string('sale_type', 120);
            $t->string('sro_schedule_no', 80)->nullable();
            $t->string('sro_item_serial_no', 80)->nullable();
            $t->date('effective_from');
            $t->date('effective_until')->nullable();
            $t->timestamps();
            $t->unique(['fiscal_configuration_id', 'product_variant_id', 'effective_from'], 'fiscal_mapping_version_unique');
        });
        Schema::create('fiscal_documents', function (Blueprint $t): void {
            $t->id();
            $t->binary('public_id', 16, true)->unique();
            $t->foreignId('merchant_id');
            $t->foreignId('store_id');
            $t->foreignId('fiscal_configuration_id')->constrained();
            $t->string('local_document_type', 40);
            $t->string('local_document_reference', 120);
            $t->string('local_document_number', 80);
            $t->string('state', 30)->default('queued');
            $t->json('payload_snapshot');
            $t->char('payload_hash', 64);
            $t->string('authority_reference', 160)->nullable();
            $t->timestamp('authority_dated_at')->nullable();
            $t->text('qr_value')->nullable();
            $t->string('failure_category', 80)->nullable();
            $t->string('failure_code', 120)->nullable();
            $t->string('safe_failure_message', 500)->nullable();
            $t->timestamp('unknown_since')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamps();
            $t->unique(['fiscal_configuration_id', 'local_document_type', 'local_document_reference'], 'fiscal_local_document_unique');
            $t->index(['merchant_id', 'store_id', 'state']);
        });
        Schema::create('fiscal_submission_attempts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('fiscal_document_id')->constrained()->cascadeOnDelete();
            $t->unsignedSmallInteger('attempt_number');
            $t->string('outcome', 30);
            $t->char('request_hash', 64);
            $t->char('response_hash', 64)->nullable();
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->string('authority_code', 120)->nullable();
            $t->string('safe_message', 500)->nullable();
            $t->timestamp('attempted_at')->useCurrent();
            $t->unique(['fiscal_document_id', 'attempt_number'], 'fiscal_attempt_number_unique');
        });
        Schema::create('fiscal_outbox_consumptions', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('outbox_event_id')->constrained()->cascadeOnDelete();
            $t->foreignId('fiscal_document_id')->nullable()->constrained();
            $t->string('outcome', 30);
            $t->timestamp('consumed_at')->useCurrent();
            $t->unique('outbox_event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_outbox_consumptions');
        Schema::dropIfExists('fiscal_submission_attempts');
        Schema::dropIfExists('fiscal_documents');
        Schema::dropIfExists('fiscal_product_mappings');
        Schema::dropIfExists('fiscal_configurations');
    }
};
