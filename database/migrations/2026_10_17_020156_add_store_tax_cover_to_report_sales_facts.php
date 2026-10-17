<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_sales_facts', function (Blueprint $table): void {
            $table->index(['merchant_id', 'store_id', 'is_return', 'tax_classification_snapshot', 'tax_rate_snapshot', 'business_date', 'document_internal_id', 'revenue', 'tax', 'sales_including_tax', 'discounts', 'cogs', 'pending_costs'], 'report_facts_store_tax_cover');
        });
    }

    public function down(): void
    {
        Schema::table('report_sales_facts', function (Blueprint $table): void {
            $table->dropIndex('report_facts_store_tax_cover');
        });
    }
};
