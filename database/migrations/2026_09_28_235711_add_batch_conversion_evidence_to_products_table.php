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
        Schema::table('products', function (Blueprint $table) {
            $table->uuid('batch_conversion_key')->nullable()->after('batch_tracking_enabled');
            $table->char('batch_conversion_hash', 64)->nullable()->after('batch_conversion_key');
            $table->unique(['merchant_id', 'batch_conversion_key'], 'products_batch_conversion_key_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_batch_conversion_key_unique');
            $table->dropColumn(['batch_conversion_key', 'batch_conversion_hash']);
        });
    }
};
