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
        Schema::create('scale_barcode_formats', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable();
            $table->unsignedBigInteger('scope_store_id')->storedAs('coalesce(`store_id`, 0)');
            $table->string('name', 120);
            $table->string('prefix', 6);
            $table->unsignedTinyInteger('total_length');
            $table->unsignedTinyInteger('item_start');
            $table->unsignedTinyInteger('item_length');
            $table->unsignedTinyInteger('value_start');
            $table->unsignedTinyInteger('value_length');
            $table->string('value_type', 20);
            $table->unsignedTinyInteger('value_decimal_places');
            $table->string('checksum', 20)->default('ean13');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['merchant_id', 'scope_store_id', 'prefix']);
            $table->foreign(['merchant_id', 'store_id'], 'scale_formats_store_owner_fk')->references(['merchant_id', 'id'])->on('stores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scale_barcode_formats');
    }
};
