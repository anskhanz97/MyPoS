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
        Schema::table('stock_transfer_lines', function (Blueprint $table) {
            $table->decimal('source_unit_cost', 19, 6)->nullable()->after('expected_destination_quantity_base');
            $table->decimal('destination_unit_cost', 19, 6)->nullable()->after('source_unit_cost');
            $table->foreignId('source_cost_entry_id')->nullable()->after('destination_unit_cost')->constrained('inventory_cost_entries');
        });
        Schema::table('stock_transfer_receipt_lines', function (Blueprint $table) {
            $table->foreignId('destination_cost_entry_id')->nullable()->constrained('inventory_cost_entries');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_transfer_receipt_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('destination_cost_entry_id');
        });
        Schema::table('stock_transfer_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_cost_entry_id');
            $table->dropColumn(['source_unit_cost', 'destination_unit_cost']);
        });
    }
};
