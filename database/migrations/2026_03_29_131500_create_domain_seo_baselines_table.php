<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('domain_seo_baselines')) {
            Schema::create('domain_seo_baselines', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('domain_id');
                $table->uuid('web_property_id')->nullable();
                $table->uuid('property_analytics_source_id')->nullable();
                $table->string('baseline_type', 40)->default('manual_checkpoint');
                $table->timestamp('captured_at');
                $table->string('captured_by')->nullable();

                $table->string('source_provider', 30)->default('matomo');
                $table->string('matomo_site_id')->nullable();
                $table->text('search_console_property_uri')->nullable();
                $table->string('search_type', 32)->default('web');
                $table->date('date_range_start')->nullable();
                $table->date('date_range_end')->nullable();
                $table->string('import_method', 40)->default('matomo_api');
                $table->string('artifact_path')->nullable();

                $table->decimal('clicks', 18, 4)->default(0);
                $table->decimal('impressions', 18, 4)->default(0);
                $table->decimal('ctr', 18, 6)->default(0);
                $table->decimal('average_position', 18, 6)->default(0);

                $table->unsignedInteger('indexed_pages')->nullable();
                $table->unsignedInteger('not_indexed_pages')->nullable();
                $table->unsignedInteger('pages_with_redirect')->nullable();
                $table->unsignedInteger('not_found_404')->nullable();
                $table->unsignedInteger('blocked_by_robots')->nullable();
                $table->unsignedInteger('alternate_with_canonical')->nullable();
                $table->unsignedInteger('crawled_currently_not_indexed')->nullable();
                $table->unsignedInteger('discovered_currently_not_indexed')->nullable();
                $table->unsignedInteger('duplicate_without_user_selected_canonical')->nullable();

                $table->unsignedInteger('top_pages_count')->nullable();
                $table->unsignedInteger('top_queries_count')->nullable();
                $table->unsignedInteger('inspected_url_count')->nullable();
                $table->unsignedInteger('inspection_indexed_url_count')->nullable();
                $table->unsignedInteger('inspection_non_indexed_url_count')->nullable();
                $table->unsignedInteger('amp_urls')->nullable();
                $table->unsignedInteger('mobile_issue_urls')->nullable();
                $table->unsignedInteger('rich_result_urls')->nullable();
                $table->unsignedInteger('rich_result_issue_urls')->nullable();

                $table->text('notes')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();

                $table->foreign('domain_id')->references('id')->on('domains')->cascadeOnDelete();

                if (Schema::hasTable('web_properties')) {
                    $table->foreign('web_property_id')->references('id')->on('web_properties')->nullOnDelete();
                }

                if (Schema::hasTable('property_analytics_sources')) {
                    $table->foreign('property_analytics_source_id')->references('id')->on('property_analytics_sources')->nullOnDelete();
                }

                $table->index(['domain_id', 'captured_at']);
                $table->index(['domain_id', 'baseline_type']);
                $table->index(['source_provider', 'matomo_site_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('domain_seo_baselines');
    }
};
