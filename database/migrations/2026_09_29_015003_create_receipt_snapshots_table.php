<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipt_snapshots', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('sale_id');
            $table->unsignedInteger('version')->default(1);
            $table->char('content_hash', 64);
            $table->json('payload');
            $table->timestamp('created_at');
            $table->unique(['sale_id', 'version']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->foreign(['merchant_id', 'store_id', 'sale_id'])->references(['merchant_id', 'store_id', 'id'])->on('sales');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_snapshots');
    }
};
