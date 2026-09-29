<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('supplier_id');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('number', 40);
            $table->string('state', 24)->default('draft');
            $table->unsignedBigInteger('version')->default(1);
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->string('source_reference', 120)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->uuid('issue_key')->nullable();
            $table->char('issue_hash', 64)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'number']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->unique(['merchant_id', 'store_id', 'issue_key']);
            $table->index(['merchant_id', 'store_id', 'state', 'order_date']);
            $table->foreign(['merchant_id', 'store_id'])->references(['merchant_id', 'id'])->on('stores');
            $table->foreign(['merchant_id', 'supplier_id'])->references(['merchant_id', 'id'])->on('suppliers');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
