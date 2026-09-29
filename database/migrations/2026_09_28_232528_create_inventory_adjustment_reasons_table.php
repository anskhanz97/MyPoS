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
        Schema::create('inventory_adjustment_reasons', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name', 160);
            $table->string('direction', 12)->default('both');
            $table->boolean('requires_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['merchant_id', 'id']);
            $table->unique(['merchant_id', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_reasons');
    }
};
