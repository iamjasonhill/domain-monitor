<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('analytics_source_observations')) {
            Schema::create('analytics_source_observations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('provider', 30);
                $table->string('external_id');
                $table->string('external_name')->nullable();
                $table->string('expected_tracker_host')->nullable();
                $table->string('install_verdict', 40)->default('unknown');
                $table->text('best_url')->nullable();
                $table->json('detected_site_ids')->nullable();
                $table->json('detected_tracker_hosts')->nullable();
                $table->text('summary')->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->uuid('matched_property_analytics_source_id')->nullable();
                $table->uuid('matched_web_property_id')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();

                $table->unique(['provider', 'external_id']);
                $table->foreign('matched_property_analytics_source_id')->references('id')->on('property_analytics_sources')->nullOnDelete();
                $table->foreign('matched_web_property_id')->references('id')->on('web_properties')->nullOnDelete();
                $table->index(['provider', 'install_verdict']);
                $table->index('matched_web_property_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_source_observations');
    }
};
