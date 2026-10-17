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
        Schema::create('report_receipt_facts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id')->constrained();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('document_internal_id');
            $table->boolean('is_return');
            $table->char('document_id', 32);
            $table->string('document_number', 80);
            $table->date('business_date');
            foreach (['revenue', 'tax', 'sales_including_tax', 'discounts', 'cogs'] as $column) {
                $table->decimal($column, 26, 6);
            }
            $table->unsignedBigInteger('pending_costs');
            $table->index(['merchant_id', 'store_id', 'business_date'], 'report_receipts_store_date');
            $table->index(['merchant_id', 'business_date'], 'report_receipts_date');
            $table->index(['merchant_id', 'document_number', 'document_id'], 'report_receipts_number');
        });
        Schema::create('report_daily_facts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('merchant_id')->constrained();
            $table->unsignedBigInteger('store_id');
            $table->date('business_date');
            $table->string('kind', 12);
            $table->boolean('is_return');
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('product')->nullable();
            $table->string('sku', 120)->nullable();
            $table->string('unit', 60)->nullable();
            $table->string('tax_classification_snapshot')->nullable();
            $table->decimal('tax_rate_snapshot', 12, 6)->nullable();
            foreach (['quantity', 'revenue', 'tax', 'sales_including_tax', 'discounts', 'cogs'] as $column) {
                $table->decimal($column, 26, 6);
            }
            $table->unsignedBigInteger('pending_costs');
            $table->unsignedBigInteger('documents');
            $table->index(['merchant_id', 'kind', 'business_date'], 'report_daily_date');
            $table->index(['merchant_id', 'store_id', 'kind', 'business_date'], 'report_daily_store_date');
            $table->index(['merchant_id', 'kind', 'store_id', 'product_variant_id', 'is_return', 'business_date'], 'report_daily_last_sale');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_daily_facts');
        Schema::dropIfExists('report_receipt_facts');
    }
};
