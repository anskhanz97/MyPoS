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
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->string('name', 160);
            $table->string('slug', 100)->unique();
            $table->string('store_data_mode', 20);
            $table->string('status', 20)->default('onboarding');
            $table->string('timezone', 64)->default('Asia/Karachi');
            $table->char('currency', 3)->default('PKR');
            $table->timestamp('store_data_mode_locked_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('merchants');
    }
};
