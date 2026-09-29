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
        Schema::create('unit_of_measures', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 24);
            $table->string('name', 80);
            $table->string('symbol', 16);
            $table->unsignedTinyInteger('decimal_places')->default(0);
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
        Schema::dropIfExists('unit_of_measures');
    }
};
