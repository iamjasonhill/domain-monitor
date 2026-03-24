<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('analytics_install_audits')) {
            Schema::create('analytics_install_audits', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('property_analytics_source_id');
                $table->uuid('web_property_id');
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
                $table->json('raw_payload')->nullable();
                $table->timestamps();

                $table->foreign('property_analytics_source_id')->references('id')->on('property_analytics_sources')->cascadeOnDelete();
                $table->foreign('web_property_id')->references('id')->on('web_properties')->cascadeOnDelete();
                $table->unique('property_analytics_source_id');
                $table->index(['provider', 'external_id']);
                $table->index(['web_property_id', 'install_verdict']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_install_audits');
    }
};
