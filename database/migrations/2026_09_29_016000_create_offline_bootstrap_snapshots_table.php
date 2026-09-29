<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_enrollments', function (Blueprint $table) {
            $table->unique(['merchant_id', 'store_id', 'id'], 'device_enrollments_store_owner_uq');
        });
        Schema::create('offline_bootstrap_snapshots', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('register_id');
            $table->foreignId('device_enrollment_id');
            $table->unsignedSmallInteger('schema_version');
            $table->string('snapshot_version', 160);
            $table->char('content_hash', 64);
            $table->json('payload');
            $table->text('signed_envelope');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['device_enrollment_id', 'snapshot_version'], 'offline_snapshots_device_version_uq');
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->index(['device_enrollment_id', 'expires_at'], 'offline_snapshots_device_expiry_idx');
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'store_id', 'register_id'], 'offline_snapshots_register_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('registers');
            $table->foreign(['merchant_id', 'store_id', 'device_enrollment_id'], 'offline_snapshots_device_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('device_enrollments');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_bootstrap_snapshots');
        Schema::table('device_enrollments', function (Blueprint $table) {
            $table->dropUnique('device_enrollments_store_owner_uq');
        });
    }
};
