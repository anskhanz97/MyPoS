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
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->foreignId('merchant_id')->constrained();
            $table->string('prefix', 12);
            $table->date('business_date');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->primary(['merchant_id', 'prefix', 'business_date'], 'document_sequences_scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
