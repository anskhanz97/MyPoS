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
        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('code', 80);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('limit_value')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entitlements');
    }
};
