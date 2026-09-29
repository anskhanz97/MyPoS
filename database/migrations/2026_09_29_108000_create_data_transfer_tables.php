<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_transfer_jobs', function (Blueprint $t): void {
            $t->id();
            $t->binary('public_id', 16, true)->unique();
            $t->foreignId('merchant_id');
            $t->foreignId('store_id')->nullable();
            $t->foreignId('actor_user_id')->constrained('users');
            $t->enum('direction', ['import', 'export']);
            $t->string('resource', 40);
            $t->string('state', 30)->default('uploaded');
            $t->unsignedSmallInteger('schema_version')->default(1);
            $t->string('original_name')->nullable();
            $t->string('disk', 40)->default('local');
            $t->string('source_path', 500)->nullable();
            $t->char('source_hash', 64)->nullable();
            $t->json('options')->nullable();
            $t->unsignedInteger('total_rows')->default(0);
            $t->unsignedInteger('valid_rows')->default(0);
            $t->unsignedInteger('invalid_rows')->default(0);
            $t->uuid('commit_key')->nullable();
            $t->timestamp('committed_at')->nullable();
            $t->string('artifact_path', 500)->nullable();
            $t->char('artifact_hash', 64)->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->text('failure_message')->nullable();
            $t->timestamps();
            $t->unique(['merchant_id', 'source_hash', 'resource'], 'transfer_source_resource_unique');
            $t->unique(['merchant_id', 'commit_key'], 'transfer_commit_key_unique');
            $t->index(['merchant_id', 'direction', 'created_at']);
        });
        Schema::create('data_transfer_rows', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('data_transfer_job_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('row_number');
            $t->json('payload');
            $t->json('errors')->nullable();
            $t->string('state', 20)->default('valid');
            $t->string('result_reference')->nullable();
            $t->timestamps();
            $t->unique(['data_transfer_job_id', 'row_number']);
        });
        Schema::create('import_mapping_presets', function (Blueprint $t): void {
            $t->id();
            $t->binary('public_id', 16, true)->unique();
            $t->foreignId('merchant_id');
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('resource', 40);
            $t->string('name', 120);
            $t->json('mapping');
            $t->timestamps();
            $t->unique(['merchant_id', 'user_id', 'resource', 'name'], 'import_mapping_preset_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_mapping_presets');
        Schema::dropIfExists('data_transfer_rows');
        Schema::dropIfExists('data_transfer_jobs');
    }
};
