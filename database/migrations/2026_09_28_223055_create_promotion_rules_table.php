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
        Schema::create('promotion_rules', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_book_version_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('name', 160);
            $table->string('code', 80)->nullable();
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('stackable')->default(false);
            $table->string('exclusive_group', 80)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('conditions');
            $table->json('reward');
            $table->unsignedInteger('maximum_redemptions')->nullable();
            $table->unsignedInteger('maximum_per_customer')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'id']);
            $table->unique(['price_book_version_id', 'code'], 'promotion_rules_version_code_unique');
            $table->index(['merchant_id', 'price_book_version_id', 'priority']);
            $table->foreign(['merchant_id', 'price_book_version_id'], 'promotion_rules_version_owner_fk')->references(['merchant_id', 'id'])->on('price_book_versions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_rules');
    }
};
