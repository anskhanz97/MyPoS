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
        Schema::create('price_book_versions', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable();
            $table->unsignedBigInteger('scope_store_id')->storedAs('coalesce(`store_id`, 0)');
            $table->unsignedInteger('version');
            $table->string('mode', 20);
            $table->char('currency', 3)->default('PKR');
            $table->timestamp('effective_from');
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('published_by_user_id')->constrained('users');
            $table->timestamp('published_at');
            $table->char('content_hash', 64);
            $table->timestamps();

            $table->unique(['merchant_id', 'id']);
            $table->unique(['merchant_id', 'scope_store_id', 'version'], 'price_versions_scope_version_unique');
            $table->index(['merchant_id', 'scope_store_id', 'effective_from'], 'price_versions_effective_index');
            $table->foreign(['merchant_id', 'store_id'], 'price_versions_store_owner_fk')->references(['merchant_id', 'id'])->on('stores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_book_versions');
    }
};
