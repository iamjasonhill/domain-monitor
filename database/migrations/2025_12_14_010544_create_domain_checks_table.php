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
        if (! Schema::hasTable('domain_checks')) {
            Schema::create('domain_checks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('domain_id');
                $table->string('check_type')->index(); // http|ssl|dns|uptime|downtime|platform|hosting
                $table->string('status')->index(); // ok|warn|fail
                $table->integer('response_code')->nullable(); // HTTP status codes
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable()->index();
                $table->integer('duration_ms')->nullable();
                $table->text('error_message')->nullable();
                $table->jsonb('payload')->nullable();
                $table->jsonb('metadata')->nullable(); // Structured data separate from payload
                $table->integer('retry_count')->default(0);
                $table->timestamps();

                // Foreign key with cascade delete
                $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');

                // Composite indexes
                $table->index(['domain_id', 'check_type', 'created_at']);
                $table->index(['status', 'finished_at']); // For finding recent failures
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_checks');
    }
};
