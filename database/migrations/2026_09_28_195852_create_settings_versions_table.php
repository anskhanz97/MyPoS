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
        Schema::create('settings_versions', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', length: 16, fixed: true)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->nullable();
            $table->unsignedBigInteger('scope_store_id')->storedAs('coalesce(`store_id`, 0)');
            $table->string('scope', 20);
            $table->unsignedInteger('version');
            $table->json('payload');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamp('published_at');
            $table->timestamps();

            $table->unique(['merchant_id', 'id']);
            $table->unique(['merchant_id', 'scope_store_id', 'version']);
            $table->foreign(['merchant_id', 'store_id'], 'settings_versions_store_owner_fk')
                ->references(['merchant_id', 'id'])
                ->on('stores');
        });

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `settings_versions` ADD CONSTRAINT `settings_versions_scope_check` CHECK ((`scope` = 'merchant' AND `store_id` IS NULL) OR (`scope` = 'store' AND `store_id` IS NOT NULL))");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings_versions');
    }
};
