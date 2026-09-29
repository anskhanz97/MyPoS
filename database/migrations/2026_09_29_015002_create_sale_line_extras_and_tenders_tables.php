<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_line_extras', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('sale_line_id');
            $table->foreignId('extra_option_id');
            $table->string('group_name_snapshot', 120);
            $table->string('option_name_snapshot', 120);
            $table->decimal('unit_price_delta', 19, 2);
            $table->foreignId('stock_variant_id')->nullable();
            $table->decimal('stock_quantity_base', 19, 6)->nullable();
            $table->unsignedInteger('sequence');
            $table->timestamps();
            $table->foreign(['merchant_id', 'store_id', 'sale_line_id'], 'sale_extras_line_owner_fk')->references(['merchant_id', 'store_id', 'id'])->on('sale_lines');
            $table->foreign(['merchant_id', 'extra_option_id'])->references(['merchant_id', 'id'])->on('extra_options');
        });
        Schema::create('sale_tenders', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id');
            $table->foreignId('sale_id');
            $table->uuid('client_tender_id');
            $table->string('type', 30);
            $table->string('state', 20)->default('accepted');
            $table->decimal('amount', 19, 2);
            $table->string('external_reference', 200)->nullable();
            $table->string('confirmation_source', 30);
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['sale_id', 'client_tender_id']);
            $table->unique(['merchant_id', 'store_id', 'id']);
            $table->foreign(['merchant_id', 'store_id', 'sale_id'])->references(['merchant_id', 'store_id', 'id'])->on('sales');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_tenders');
        Schema::dropIfExists('sale_line_extras');
    }
};
