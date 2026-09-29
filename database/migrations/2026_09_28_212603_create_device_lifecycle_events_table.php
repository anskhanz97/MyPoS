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
        Schema::create('device_lifecycle_events', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('store_id');
            $table->foreignId('device_enrollment_id');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_code', 80);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->foreign(['merchant_id', 'store_id'], 'device_events_store_owner_fk')->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'device_enrollment_id'], 'device_events_device_owner_fk')->references(['merchant_id', 'id'])->on('device_enrollments');
            $table->index(['merchant_id', 'device_enrollment_id', 'occurred_at'], 'device_events_timeline_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_lifecycle_events');
    }
};
