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
        Schema::create('domain_compliance_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('domain_id');
            $table->boolean('is_compliant')->default(true);
            $table->text('compliance_reason')->nullable();
            $table->string('source')->default('synergy'); // 'synergy', 'manual', etc.
            $table->timestamp('checked_at');
            $table->json('payload')->nullable(); // Store full API response for audit
            $table->timestamps();

            // Foreign key with cascade delete
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');

            // Indexes
            $table->index(['domain_id', 'checked_at']);
            $table->index(['is_compliant', 'checked_at']);
            $table->index('checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_compliance_checks');
    }
};
