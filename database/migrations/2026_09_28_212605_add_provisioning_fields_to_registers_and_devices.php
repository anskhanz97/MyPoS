<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('registers', function (Blueprint $table) {
            $table->json('capabilities')->nullable()->after('status');
            $table->unsignedBigInteger('fencing_epoch')->default(1)->after('capabilities');
        });
        Schema::table('device_enrollments', function (Blueprint $table) {
            $table->unsignedInteger('credential_version')->default(1)->after('device_key_hash');
            $table->unsignedBigInteger('fencing_epoch')->default(1)->after('credential_version');
            $table->unsignedInteger('settings_version')->default(1)->after('fencing_epoch');
            $table->unsignedSmallInteger('protocol_min')->default(1)->after('settings_version');
            $table->unsignedSmallInteger('protocol_max')->default(1)->after('protocol_min');
            $table->timestamp('lease_expires_at')->nullable()->after('last_seen_at');
            $table->foreignId('revoked_by_user_id')->nullable()->after('revoked_at')->constrained('users')->nullOnDelete();
            $table->text('revoke_reason')->nullable()->after('revoked_by_user_id');
        });
        Schema::table('settings_versions', function (Blueprint $table) {
            $table->timestamp('effective_at')->nullable()->after('published_at');
        });
        DB::table('settings_versions')->whereNull('effective_at')->update(['effective_at' => DB::raw('published_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings_versions', fn (Blueprint $table) => $table->dropColumn('effective_at'));
        Schema::table('device_enrollments', function (Blueprint $table) {
            $table->dropForeign(['revoked_by_user_id']);
            $table->dropColumn(['credential_version', 'fencing_epoch', 'settings_version', 'protocol_min', 'protocol_max', 'lease_expires_at', 'revoked_by_user_id', 'revoke_reason']);
        });
        Schema::table('registers', fn (Blueprint $table) => $table->dropColumn(['capabilities', 'fencing_epoch']));
    }
};
