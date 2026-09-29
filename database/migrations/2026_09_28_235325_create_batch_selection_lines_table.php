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
        Schema::create('batch_selection_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('batch_selection_snapshot_id');
            $table->foreignId('product_batch_id');
            $table->decimal('quantity_base', 19, 6);
            $table->date('expiry_date_snapshot');
            $table->unsignedInteger('sequence');
            $table->timestamps();

            $table->unique(['batch_selection_snapshot_id', 'product_batch_id'], 'batch_selection_lines_batch_unique');
            $table->unique(['batch_selection_snapshot_id', 'sequence'], 'batch_selection_lines_sequence_unique');
            $table->foreign(['merchant_id', 'store_id', 'batch_selection_snapshot_id'], 'batch_selection_lines_snapshot_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('batch_selection_snapshots')->cascadeOnDelete();
            $table->foreign(['merchant_id', 'product_batch_id'], 'batch_selection_lines_batch_owner_fk')->references(['merchant_id', 'id'])->on('product_batches');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('batch_selection_lines');
    }
};
