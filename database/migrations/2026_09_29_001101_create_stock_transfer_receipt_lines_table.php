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
        Schema::create('stock_transfer_receipt_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id');
            $table->foreignId('stock_transfer_receipt_id');
            $table->foreignId('stock_transfer_line_id');
            $table->foreignId('destination_product_batch_id')->nullable();
            $table->decimal('received_destination_quantity_base', 19, 6);
            $table->timestamps();
            $table->unique(['stock_transfer_receipt_id', 'stock_transfer_line_id'], 'stock_transfer_receipt_lines_transfer_line_unique');
            $table->foreign(['merchant_id', 'stock_transfer_receipt_id'], 'stock_transfer_receipt_lines_receipt_owner_fk')->references(['merchant_id', 'id'])->on('stock_transfer_receipts')->cascadeOnDelete();
            $table->foreign(['merchant_id', 'stock_transfer_line_id'], 'stock_transfer_receipt_lines_line_owner_fk')->references(['merchant_id', 'id'])->on('stock_transfer_lines');
            $table->foreign(['merchant_id', 'destination_product_batch_id'], 'stock_transfer_receipt_lines_batch_owner_fk')->references(['merchant_id', 'id'])->on('product_batches');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_receipt_lines');
    }
};
