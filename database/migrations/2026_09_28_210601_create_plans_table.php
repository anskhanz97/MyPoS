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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->string('code', 40)->unique();
            $table->string('name', 120);
            $table->unsignedSmallInteger('billing_period_days')->default(30);
            $table->unsignedSmallInteger('grace_days')->default(7);
            $table->decimal('price', 14, 2);
            $table->char('currency', 3)->default('PKR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
