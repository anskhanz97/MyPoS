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
        Schema::create('loyalty_customer_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id');
            $table->foreignId('loyalty_member_id');
            $table->foreignId('operational_customer_id');
            $table->timestamps();

            $table->unique('operational_customer_id');
            $table->foreign(['merchant_id', 'loyalty_member_id'], 'loyalty_customer_links_member_owner_fk')
                ->references(['merchant_id', 'id'])
                ->on('loyalty_members')
                ->cascadeOnDelete();
            $table->foreign(['merchant_id', 'operational_customer_id'], 'loyalty_customer_links_customer_owner_fk')
                ->references(['merchant_id', 'id'])
                ->on('operational_customers')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loyalty_customer_links');
    }
};
