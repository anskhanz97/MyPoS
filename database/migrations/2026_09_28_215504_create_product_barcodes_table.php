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
        Schema::create('product_barcodes', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id')->nullable();
            $table->unsignedBigInteger('scope_store_id')->storedAs('coalesce(`store_id`, 0)');
            $table->foreignId('product_variant_id');
            $table->string('code', 64);
            $table->string('kind', 20)->default('unit');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['merchant_id', 'scope_store_id', 'code']);
            $table->foreign(['merchant_id', 'product_variant_id'], 'barcodes_variant_owner_fk')->references(['merchant_id', 'id'])->on('product_variants')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_barcodes');
    }
};
