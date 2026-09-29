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
        Schema::create('registers', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id');
            $table->string('name', 120);
            $table->string('code', 40);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['merchant_id', 'id']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->unique(['merchant_id', 'store_id', 'code']);
            $table->foreign(['merchant_id', 'store_id'], 'registers_store_owner_fk')->references(['merchant_id', 'id'])->on('stores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registers');
    }
};
