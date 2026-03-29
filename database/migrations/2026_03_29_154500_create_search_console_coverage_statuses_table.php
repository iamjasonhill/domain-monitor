<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('search_console_coverage_statuses')) {
            Schema::create('search_console_coverage_statuses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('domain_id')->nullable();
                $table->uuid('web_property_id')->nullable();
                $table->uuid('property_analytics_source_id')->nullable();
                $table->string('source_provider', 30)->default('matomo');
                $table->string('matomo_site_id');
                $table->string('matomo_site_name')->nullable();
                $table->text('matomo_main_url')->nullable();
                $table->string('mapping_state', 32)->default('not_mapped');
                $table->text('property_uri')->nullable();
                $table->string('property_type', 32)->nullable();
                $table->timestamp('mapped_at')->nullable();
                $table->timestamp('latest_completed_job_at')->nullable();
                $table->string('latest_completed_job_type', 32)->nullable();
                $table->date('latest_completed_range_end')->nullable();
                $table->date('latest_metric_date')->nullable();
                $table->timestamp('checked_at');
                $table->json('raw_payload')->nullable();
                $table->timestamps();

                if (Schema::hasTable('domains')) {
                    $table->foreign('domain_id')->references('id')->on('domains')->nullOnDelete();
                }

                if (Schema::hasTable('web_properties')) {
                    $table->foreign('web_property_id')->references('id')->on('web_properties')->nullOnDelete();
                }

                if (Schema::hasTable('property_analytics_sources')) {
                    $table->foreign('property_analytics_source_id')->references('id')->on('property_analytics_sources')->nullOnDelete();
                }

                $table->unique(['source_provider', 'matomo_site_id']);
                $table->index(['mapping_state', 'latest_metric_date']);
                $table->index(['domain_id', 'checked_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_coverage_statuses');
    }
};
