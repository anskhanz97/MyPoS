<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['product_categories', 'reporting_categories', 'product_brands', 'product_reporting_types', 'register_groups'] as $name) {
            if (Schema::hasTable($name)) {
                continue;
            }
            Schema::create($name, function (Blueprint $t): void {
                $t->id();
                $t->binary('public_id', 16, true)->unique();
                $t->foreignId('merchant_id')->constrained();
                $t->foreignId('store_id')->nullable()->constrained();
                $t->unsignedBigInteger('scope_store_id')->storedAs('COALESCE(store_id, 0)');
                $t->string('name', 120);
                $t->string('code', 60);
                $t->string('classification', 20)->nullable();
                $t->timestamp('archived_at')->nullable();
                $t->timestamps();
                $t->unique(['merchant_id', 'scope_store_id', 'code']);
                $t->unique(['merchant_id', 'id']);
            });
        }
        if (! Schema::hasColumn('products', 'product_category_id')) {
            Schema::table('products', function (Blueprint $t): void {
                foreach (['product_category_id' => 'product_categories', 'reporting_category_id' => 'reporting_categories', 'product_brand_id' => 'product_brands', 'product_reporting_type_id' => 'product_reporting_types', 'primary_supplier_id' => 'suppliers'] as $column => $target) {
                    $t->foreignId($column)->nullable();
                    $t->foreign(['merchant_id', $column])->references(['merchant_id', 'id'])->on($target);
                }
                $t->boolean('is_miscellaneous')->default(false);
            });
        }
        if (! Schema::hasColumn('registers', 'register_group_id')) {
            Schema::table('registers', function (Blueprint $t): void {
                $t->foreignId('register_group_id')->nullable();
                $t->foreign(['merchant_id', 'register_group_id'])->references(['merchant_id', 'id'])->on('register_groups');
            });
        }
        if (! Schema::hasColumn('sales', 'reporting_snapshot')) {
            Schema::table('sales', function (Blueprint $t): void {
                $t->string('reporting_timezone', 64)->nullable();
                $t->unsignedBigInteger('register_group_id')->nullable();
                $t->unsignedBigInteger('customer_group_id')->nullable();
                $t->json('reporting_snapshot')->nullable();
                $t->index(['merchant_id', 'business_date', 'created_by_user_id', 'id'], 'sales_report_scope');
            });
        }
        if (! Schema::hasColumn('sale_lines', 'reporting_snapshot')) {
            Schema::table('sale_lines', function (Blueprint $t): void {
                foreach (['product_category_id', 'reporting_category_id', 'product_brand_id', 'product_reporting_type_id', 'primary_supplier_id'] as $column) {
                    $t->unsignedBigInteger($column)->nullable();
                    $t->index(['merchant_id', $column, 'sale_id']);
                }
                $t->string('reporting_size', 80)->nullable();
                $t->boolean('is_miscellaneous')->default(false);
                $t->decimal('tax_rate_snapshot', 12, 6)->nullable();
                $t->string('tax_classification_snapshot', 80)->nullable();
                $t->json('reporting_snapshot')->nullable();
            });
        }
        if (! Schema::hasColumn('stock_movements', 'sale_line_id')) {
            Schema::table('stock_movements', function (Blueprint $t): void {
                $t->foreignId('sale_line_id')->nullable()->constrained();
                $t->foreignId('sale_return_line_id')->nullable()->constrained();
                $t->index(['merchant_id', 'store_id', 'created_at', 'id'], 'stock_report_posting');
            });
        }
        if (! Schema::hasTable('inventory_reorder_policies')) {
            Schema::create('inventory_reorder_policies', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('merchant_id')->constrained();
                $t->foreignId('store_id')->constrained();
                $t->foreignId('product_variant_id')->constrained();
                $t->decimal('minimum_quantity', 19, 6);
                $t->decimal('target_quantity', 19, 6)->nullable();
                $t->timestamps();
                $t->unique(['store_id', 'product_variant_id']);
            });
        }
        if (! Schema::hasTable('sale_discount_allocations')) {
            Schema::create('sale_discount_allocations', function (Blueprint $t): void {
                $t->id();
                $t->foreignId('merchant_id')->constrained();
                $t->foreignId('store_id')->constrained();
                $t->foreignId('sale_line_id')->constrained();
                $t->foreignId('sale_return_line_id')->nullable()->constrained();
                $t->string('source', 40);
                $t->string('rule_id', 40)->nullable();
                $t->string('rule_name', 160)->nullable();
                $t->decimal('amount', 19, 2);
                $t->date('business_date');
                $t->index(['merchant_id', 'store_id', 'business_date'], 'discount_report_scope');
            });
        }
        if (! Schema::hasIndex('sale_discount_allocations', 'discount_report_scope')) {
            Schema::table('sale_discount_allocations', function (Blueprint $t): void {
                $t->index(['merchant_id', 'store_id', 'business_date'], 'discount_report_scope');
            });
        }
        foreach (['merchant_id' => 'merchants', 'store_id' => 'stores', 'sale_line_id' => 'sale_lines', 'sale_return_line_id' => 'sale_return_lines'] as $column => $target) {
            if (! collect(Schema::getForeignKeys('sale_discount_allocations'))->contains(fn (array $key): bool => $key['columns'] === [$column])) {
                Schema::table('sale_discount_allocations', function (Blueprint $t) use ($column, $target): void {
                    $t->foreign($column)->references('id')->on($target);
                });
            }
        }
        if (! Schema::hasTable('cart_audit_events')) {
            Schema::create('cart_audit_events', function (Blueprint $t): void {
                $t->id();
                $t->binary('public_id', 16, true)->unique();
                $t->foreignId('merchant_id')->constrained();
                $t->foreignId('store_id')->constrained();
                $t->foreignId('register_id')->constrained();
                $t->foreignId('cart_id')->constrained();
                $t->foreignId('actor_user_id')->nullable()->constrained('users');
                $t->unsignedInteger('cart_version');
                $t->string('event', 40);
                $t->string('subject', 200);
                $t->decimal('quantity_delta', 19, 6);
                $t->string('reason', 500)->nullable();
                $t->json('snapshot');
                $t->timestamp('occurred_at');
                $t->index(['merchant_id', 'store_id', 'occurred_at', 'id'], 'cart_audit_report_scope');
            });
        }
        if (! Schema::hasTable('cash_closeout_snapshots')) {
            Schema::create('cash_closeout_snapshots', function (Blueprint $t): void {
                $t->id();
                $t->binary('public_id', 16, true)->unique();
                $t->foreignId('merchant_id')->constrained();
                $t->foreignId('store_id')->constrained();
                $t->foreignId('cash_session_id')->constrained();
                $t->unsignedInteger('revision');
                $t->unsignedBigInteger('movement_high_watermark');
                $t->json('payload');
                $t->char('content_hash', 64);
                $t->timestamp('occurred_at');
                $t->unique(['cash_session_id', 'revision']);
                $t->index(['merchant_id', 'store_id', 'occurred_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_closeout_snapshots');
        Schema::dropIfExists('cart_audit_events');
        Schema::dropIfExists('sale_discount_allocations');
        Schema::dropIfExists('inventory_reorder_policies');
        Schema::table('stock_movements', function (Blueprint $t): void {
            $t->dropConstrainedForeignId('sale_line_id');
            $t->dropConstrainedForeignId('sale_return_line_id');
            $t->dropIndex('stock_report_posting');
        });
        Schema::table('sale_lines', function (Blueprint $t): void {
            foreach (['product_category_id', 'reporting_category_id', 'product_brand_id', 'product_reporting_type_id', 'primary_supplier_id'] as $column) {
                $t->dropIndex(['merchant_id', $column, 'sale_id']);
            }
            $t->dropColumn(['product_category_id', 'reporting_category_id', 'product_brand_id', 'product_reporting_type_id', 'primary_supplier_id', 'reporting_size', 'is_miscellaneous', 'tax_rate_snapshot', 'tax_classification_snapshot', 'reporting_snapshot']);
        });
        Schema::table('sales', function (Blueprint $t): void {
            $t->dropIndex('sales_report_scope');
            $t->dropColumn(['reporting_timezone', 'register_group_id', 'customer_group_id', 'reporting_snapshot']);
        });
        Schema::table('registers', function (Blueprint $t): void {
            $t->dropForeign(['merchant_id', 'register_group_id']);
            $t->dropColumn('register_group_id');
        });
        Schema::table('products', function (Blueprint $t): void {
            foreach (['product_category_id', 'reporting_category_id', 'product_brand_id', 'product_reporting_type_id', 'primary_supplier_id'] as $column) {
                $t->dropForeign(['merchant_id', $column]);
                $t->dropColumn($column);
            }
            $t->dropColumn('is_miscellaneous');
        });
        foreach (['register_groups', 'product_reporting_types', 'product_brands', 'reporting_categories', 'product_categories'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
