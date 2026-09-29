<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('source', 20)->default('online')->after('state');
            $table->index(['store_id', 'source', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['store_id', 'source', 'business_date']);
            $table->dropColumn('source');
        });
    }
};
