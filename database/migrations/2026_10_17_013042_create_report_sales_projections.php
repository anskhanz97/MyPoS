<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_projection_states', function (Blueprint $t): void {
            $t->foreignId('merchant_id')->primary()->constrained();
            $t->json('watermark');
            $t->timestamp('built_at');
        });
        Schema::create('report_sales_facts', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('merchant_id')->constrained();
            foreach (['store_id', 'document_internal_id', 'register_id', 'cash_session_id', 'employee_id', 'product_variant_id'] as $column) {
                $t->unsignedBigInteger($column);
            }
            foreach (['customer_id', 'customer_group_id', 'register_group_id', 'product_category_id', 'reporting_category_id', 'product_brand_id', 'product_reporting_type_id', 'primary_supplier_id'] as $column) {
                $t->unsignedBigInteger($column)->nullable();
            }
            $t->char('document_id', 32);
            $t->string('document_number', 80);
            $t->date('business_date');
            $t->dateTime('occurred_at');
            $t->string('product');
            $t->string('sku', 120);
            $t->string('unit', 60);
            $t->string('reporting_size', 120)->nullable();
            $t->string('tax_classification_snapshot')->nullable();
            $t->decimal('tax_rate_snapshot', 12, 6)->nullable();
            $t->string('reporting_timezone', 64)->nullable();
            $t->integer('reporting_offset_minutes')->nullable();
            $t->boolean('is_miscellaneous');
            $t->boolean('is_return');
            foreach (['quantity', 'revenue', 'tax', 'sales_including_tax', 'discounts', 'cogs'] as $column) {
                $t->decimal($column, 26, 6);
            }
            $t->unsignedBigInteger('pending_costs');
            $t->json('line_snapshot')->nullable();
            $t->json('header_snapshot')->nullable();
            $t->index(['merchant_id', 'store_id', 'business_date'], 'report_facts_store_date');
            $t->index(['merchant_id', 'business_date'], 'report_facts_merchant_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_sales_facts');
        Schema::dropIfExists('report_projection_states');
    }
};
