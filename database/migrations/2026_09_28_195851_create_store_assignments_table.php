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
        Schema::create('store_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id');
            $table->foreignId('merchant_membership_id');
            $table->foreignId('store_id');
            $table->timestamps();

            $table->unique(['merchant_membership_id', 'store_id']);
            $table->foreign(['merchant_id', 'merchant_membership_id'], 'store_assignments_membership_owner_fk')
                ->references(['merchant_id', 'id'])
                ->on('merchant_memberships')
                ->cascadeOnDelete();
            $table->foreign(['merchant_id', 'store_id'], 'store_assignments_store_owner_fk')
                ->references(['merchant_id', 'id'])
                ->on('stores')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_assignments');
    }
};
