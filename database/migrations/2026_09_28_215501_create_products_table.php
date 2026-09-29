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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable();
            $table->unsignedBigInteger('scope_store_id')->storedAs('coalesce(`store_id`, 0)');
            $table->string('type', 20);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('tax_classification_reference', 80)->nullable();
            $table->boolean('batch_tracking_enabled')->default(false);
            $table->unsignedInteger('structure_version')->default(1);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'id']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->index(['merchant_id', 'scope_store_id', 'archived_at', 'name'], 'products_catalog_search_idx');
            $table->foreign(['merchant_id', 'store_id'], 'products_store_owner_fk')->references(['merchant_id', 'id'])->on('stores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
