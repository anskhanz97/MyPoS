<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['application_logs', 'api_request_logs', 'integration_logs'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                $table->unsignedBigInteger('merchant_id')->nullable();
                $table->unsignedBigInteger('store_id')->nullable();
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->uuid('request_id')->nullable();
                $table->string('operation_id', 100)->nullable();
                $table->string('level', 16)->default('info');
                $table->string('event', 100);
                $table->string('status', 30);
                $table->string('message', 500);
                $table->string('subject', 120)->nullable();
                $table->string('build_version', 100);
                $table->json('context')->nullable();
                $table->dateTime('occurred_at', 6);
                if ($name === 'integration_logs') {
                    $table->string('provider', 40);
                    $table->index(['merchant_id', 'provider', 'id']);
                }
                if ($name === 'api_request_logs') {
                    $table->string('method', 12);
                    $table->string('route', 255);
                    $table->unsignedSmallInteger('http_status');
                    $table->unsignedInteger('duration_ms');
                }
                $table->index(['merchant_id', 'id']);
                $table->index(['merchant_id', 'operation_id', 'id']);
                $table->index(['merchant_id', 'request_id', 'id']);
                $table->index('occurred_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_logs');
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('application_logs');
    }
};
