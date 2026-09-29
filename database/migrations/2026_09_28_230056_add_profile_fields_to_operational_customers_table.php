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
        Schema::table('operational_customers', function (Blueprint $table) {
            $table->foreignId('customer_group_id')->nullable()->after('store_id');
            $table->string('normalized_phone', 32)->nullable()->after('phone');
            $table->string('email', 254)->nullable()->after('normalized_phone');
            $table->string('normalized_email', 254)->nullable()->after('email');
            $table->string('address_line', 240)->nullable()->after('normalized_email');
            $table->string('city', 120)->nullable()->after('address_line');
            $table->string('postal_code', 20)->nullable()->after('city');
            $table->boolean('email_receipt_preference')->default(false)->after('postal_code');
            $table->boolean('marketing_email_consent')->default(false)->after('email_receipt_preference');
            $table->timestamp('marketing_consent_at')->nullable()->after('marketing_email_consent');
            $table->string('marketing_consent_source', 80)->nullable()->after('marketing_consent_at');
            $table->timestamp('archived_at')->nullable()->after('marketing_consent_source');
            $table->unsignedInteger('version')->default(1)->after('archived_at');

            $table->index(['merchant_id', 'scope_store_id', 'normalized_phone'], 'customers_scope_phone_index');
            $table->index(['merchant_id', 'scope_store_id', 'normalized_email'], 'customers_scope_email_index');
            $table->foreign(['merchant_id', 'customer_group_id'], 'customers_group_owner_fk')->references(['merchant_id', 'id'])->on('customer_groups');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operational_customers', function (Blueprint $table) {
            $table->dropForeign('customers_group_owner_fk');
            $table->dropIndex('customers_scope_phone_index');
            $table->dropIndex('customers_scope_email_index');
            $table->dropColumn(['customer_group_id', 'normalized_phone', 'email', 'normalized_email', 'address_line', 'city', 'postal_code', 'email_receipt_preference', 'marketing_email_consent', 'marketing_consent_at', 'marketing_consent_source', 'archived_at', 'version']);
        });
    }
};
