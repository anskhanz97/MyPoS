<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_report_filters', function (Blueprint $t): void {
            $t->id();
            $t->binary('public_id', 16, true)->unique();
            $t->foreignId('merchant_id');
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('report_key', 40);
            $t->string('name', 120);
            $t->json('filters');
            $t->timestamps();
            $t->unique(['merchant_id', 'user_id', 'report_key', 'name'], 'saved_report_filter_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_report_filters');
    }
};
