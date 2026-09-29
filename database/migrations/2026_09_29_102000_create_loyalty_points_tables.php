<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_programs', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id')->unique()->constrained('merchants');
            $table->string('slug', 80)->unique();
            $table->string('name', 160);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('loyalty_program_versions', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs');
            $table->unsignedInteger('version');
            $table->decimal('points_per_pkr', 19, 8);
            $table->unsignedSmallInteger('expiry_days')->nullable();
            $table->foreignId('published_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_at');
            $table->timestamps();
            $table->unique(['loyalty_program_id', 'version']);
            $table->index(['merchant_id', 'effective_at']);
        });

        Schema::create('loyalty_rewards', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('loyalty_program_id')->constrained('loyalty_programs');
            $table->string('name', 160);
            $table->decimal('points_required', 19, 4);
            $table->decimal('discount_amount', 19, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->unique(['merchant_id', 'id']);
        });

        Schema::create('loyalty_point_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id');
            $table->foreignId('loyalty_member_id')->unique()->constrained('loyalty_members');
            $table->decimal('balance', 19, 4)->default(0);
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamps();
            $table->unique(['merchant_id', 'loyalty_member_id']);
        });

        Schema::create('loyalty_point_entries', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id')->nullable()->constrained('stores');
            $table->foreignId('loyalty_member_id')->constrained('loyalty_members');
            $table->foreignId('loyalty_program_version_id')->nullable()->constrained('loyalty_program_versions');
            $table->foreignId('related_entry_id')->nullable()->constrained('loyalty_point_entries');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 40);
            $table->decimal('points', 19, 4);
            $table->decimal('eligible_net', 19, 2)->nullable();
            $table->string('source_type', 40);
            $table->string('source_reference', 80);
            $table->string('reason', 500)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->unique(['merchant_id', 'source_type', 'source_reference', 'kind'], 'loyalty_entry_source_unique');
            $table->index(['loyalty_member_id', 'occurred_at']);
            $table->index(['kind', 'expires_at']);
        });

        Schema::create('loyalty_point_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id');
            $table->foreignId('loyalty_member_id')->constrained('loyalty_members');
            $table->foreignId('debit_entry_id')->constrained('loyalty_point_entries');
            $table->foreignId('earn_entry_id')->constrained('loyalty_point_entries');
            $table->decimal('points', 19, 4);
            $table->timestamps();
            $table->unique(['debit_entry_id', 'earn_entry_id'], 'loyalty_consumption_pair_unique');
            $table->index(['earn_entry_id', 'id']);
        });

        Schema::create('loyalty_redemption_reservations', function (Blueprint $table) {
            $table->id();
            $table->binary('public_id', 16, true)->unique();
            $table->foreignId('merchant_id');
            $table->foreignId('store_id')->constrained('stores');
            $table->foreignId('loyalty_member_id')->constrained('loyalty_members');
            $table->foreignId('loyalty_reward_id')->constrained('loyalty_rewards');
            $table->foreignId('cart_id')->unique()->constrained('carts');
            $table->foreignId('sale_id')->nullable()->unique()->constrained('sales');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('idempotency_key');
            $table->char('payload_hash', 64);
            $table->string('state', 24);
            $table->decimal('points', 19, 4);
            $table->decimal('discount_amount', 19, 2);
            $table->timestamp('expires_at');
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'idempotency_key'], 'loyalty_reservation_idempotency_unique');
            $table->index(['loyalty_member_id', 'state', 'expires_at'], 'loyalty_reservation_availability_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_redemption_reservations');
        Schema::dropIfExists('loyalty_point_consumptions');
        Schema::dropIfExists('loyalty_point_entries');
        Schema::dropIfExists('loyalty_point_balances');
        Schema::dropIfExists('loyalty_rewards');
        Schema::dropIfExists('loyalty_program_versions');
        Schema::dropIfExists('loyalty_programs');
    }
};
