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
        Schema::create('domain_eligibility_checks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('domain_id')->index();
            $table->string('source')->default('synergy')->index();
            $table->string('eligibility_type')->nullable();
            $table->boolean('is_valid')->nullable()->index();
            $table->timestamp('checked_at')->index();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['domain_id', 'checked_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_eligibility_checks');
    }
};
