<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_returns', function (Blueprint $table): void {
            $table->json('reporting_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sale_returns', function (Blueprint $table): void {
            $table->dropColumn('reporting_snapshot');
        });
    }
};
