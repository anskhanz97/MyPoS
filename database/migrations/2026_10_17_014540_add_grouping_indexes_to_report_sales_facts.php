<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_sales_facts', function (Blueprint $table): void {
            $metrics = ['document_internal_id', 'product', 'sku', 'quantity', 'revenue', 'tax', 'sales_including_tax', 'discounts', 'cogs', 'pending_costs'];
            $table->index(['merchant_id', 'store_id', 'is_return', 'product_variant_id', 'unit', 'business_date', ...$metrics], 'report_facts_store_product_cover');
            $table->index(['merchant_id', 'is_return', 'product_variant_id', 'unit', 'business_date', 'store_id', ...$metrics], 'report_facts_merchant_product_cover');
        });
    }

    public function down(): void
    {
        Schema::table('report_sales_facts', function (Blueprint $table): void {
            $table->dropIndex('report_facts_store_product_cover');
            $table->dropIndex('report_facts_merchant_product_cover');
        });
    }
};
