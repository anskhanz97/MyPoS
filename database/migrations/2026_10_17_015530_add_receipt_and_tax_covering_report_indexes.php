<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_sales_facts', function (Blueprint $table): void {
            $metrics = ['revenue', 'tax', 'sales_including_tax', 'discounts', 'cogs', 'pending_costs'];
            $table->index(['merchant_id', 'store_id', 'is_return', 'document_internal_id', 'business_date', 'document_id', 'document_number', ...$metrics], 'report_facts_store_receipt_cover');
            $table->index(['merchant_id', 'is_return', 'document_internal_id', 'store_id', 'business_date', 'document_id', 'document_number', ...$metrics], 'report_facts_merchant_receipt_cover');
            $table->index(['merchant_id', 'is_return', 'tax_classification_snapshot', 'tax_rate_snapshot', 'business_date', 'store_id', 'document_internal_id', ...$metrics], 'report_facts_tax_cover');
        });
    }

    public function down(): void
    {
        Schema::table('report_sales_facts', function (Blueprint $table): void {
            foreach (['report_facts_store_receipt_cover', 'report_facts_merchant_receipt_cover', 'report_facts_tax_cover'] as $name) {
                $table->dropIndex($name);
            }
        });
    }
};
