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
        Schema::create('domain_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('domain_id');
            $table->string('alert_type')->index(); // downtime|ssl_expiring|dns_changed
            $table->string('severity')->index(); // info|warn|critical
            $table->timestampTz('triggered_at');
            $table->timestampTz('resolved_at')->nullable()->index();
            $table->timestampTz('notification_sent_at')->nullable();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->boolean('auto_resolve')->default(false);
            $table->jsonb('payload')->nullable();
            $table->timestamps();

            // Foreign key with cascade delete
            $table->foreign('domain_id')->references('id')->on('domains')->onDelete('cascade');

            // Composite indexes
            $table->index(['domain_id', 'alert_type', 'resolved_at']);
            $table->index(['severity', 'resolved_at']); // For critical unresolved alerts
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('domain_alerts');
    }
};
