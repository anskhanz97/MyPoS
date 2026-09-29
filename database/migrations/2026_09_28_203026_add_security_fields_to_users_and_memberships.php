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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('password');
            $table->timestamp('deactivated_at')->nullable()->after('is_active');
        });

        Schema::table('merchant_memberships', function (Blueprint $table) {
            $table->string('pin_hash')->nullable()->after('status');
            $table->timestamp('pin_changed_at')->nullable()->after('pin_hash');
            $table->timestamp('revoked_at')->nullable()->after('pin_changed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('merchant_memberships', function (Blueprint $table) {
            $table->dropColumn(['pin_hash', 'pin_changed_at', 'revoked_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'deactivated_at']);
        });
    }
};
