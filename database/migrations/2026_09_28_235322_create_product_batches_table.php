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
        Schema::create('product_batches', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('product_variant_id');
            $table->foreignId('origin_store_id')->nullable();
            $table->string('code', 100);
            $table->date('expiry_date');
            $table->date('received_on')->nullable();
            $table->string('supplier_reference', 120)->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'id']);
            $table->unique(['merchant_id', 'product_variant_id', 'id']);
            $table->unique(['merchant_id', 'product_variant_id', 'code'], 'product_batches_variant_code_unique');
            $table->index(['merchant_id', 'expiry_date', 'archived_at'], 'product_batches_expiry_lookup_idx');
            $table->foreign(['merchant_id', 'product_variant_id'], 'product_batches_variant_owner_fk')->references(['merchant_id', 'id'])->on('product_variants');
            $table->foreign(['merchant_id', 'origin_store_id'], 'product_batches_origin_store_fk')->references(['merchant_id', 'id'])->on('stores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
