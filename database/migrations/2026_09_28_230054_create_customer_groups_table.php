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
        Schema::create('customer_groups', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable();
            $table->unsignedBigInteger('scope_store_id')->storedAs('coalesce(`store_id`, 0)');
            $table->string('code', 80);
            $table->string('name', 160);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'id']);
            $table->unique(['merchant_id', 'scope_store_id', 'code'], 'customer_groups_scope_code_unique');
            $table->foreign(['merchant_id', 'store_id'], 'customer_groups_store_owner_fk')->references(['merchant_id', 'id'])->on('stores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_groups');
    }
};
