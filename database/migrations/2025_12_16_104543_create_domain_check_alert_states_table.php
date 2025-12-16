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
        Schema::create('domain_check_alert_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('domain_id')->index();
            $table->string('check_type')->index();
            $table->unsignedInteger('consecutive_failure_count')->default(0);
            $table->boolean('alert_active')->default(false);
            $table->timestamp('alerted_at')->nullable();
            $table->timestamp('recovered_at')->nullable();
            $table->timestamps();

            $table->unique(['domain_id', 'check_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_check_alert_states');
    }
};
