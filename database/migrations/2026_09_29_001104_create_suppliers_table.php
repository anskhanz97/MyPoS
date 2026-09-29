<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->string('code', 40);
            $table->string('name', 160);
            $table->string('contact_name', 160)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 254)->nullable();
            $table->string('tax_reference', 80)->nullable();
            $table->string('address_line_1', 200)->nullable();
            $table->string('address_line_2', 200)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'code']);
            $table->unique(['merchant_id', 'id']);
            $table->index(['merchant_id', 'archived_at', 'name']);
            $table->foreign('merchant_id')->references('id')->on('merchants');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
