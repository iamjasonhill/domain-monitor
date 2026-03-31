<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('search_console_issue_snapshots')) {
            Schema::create('search_console_issue_snapshots', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('domain_id');
                $table->uuid('web_property_id');
                $table->uuid('property_analytics_source_id')->nullable();
                $table->string('issue_class', 100);
                $table->string('source_issue_label')->nullable();
                $table->string('capture_method', 32);
                $table->string('source_report', 80)->nullable();
                $table->text('source_property')->nullable();
                $table->string('artifact_path')->nullable();
                $table->timestamp('captured_at');
                $table->string('captured_by')->nullable();
                $table->date('first_detected_at')->nullable();
                $table->date('last_updated_at')->nullable();
                $table->string('property_scope', 120)->nullable();
                $table->unsignedInteger('affected_url_count')->nullable();
                $table->json('sample_urls')->nullable();
                $table->json('examples')->nullable();
                $table->json('chart_points')->nullable();
                $table->json('normalized_payload')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();

                $table->foreign('domain_id')->references('id')->on('domains')->cascadeOnDelete();

                if (Schema::hasTable('web_properties')) {
                    $table->foreign('web_property_id')->references('id')->on('web_properties')->cascadeOnDelete();
                }

                if (Schema::hasTable('property_analytics_sources')) {
                    $table->foreign('property_analytics_source_id')->references('id')->on('property_analytics_sources')->nullOnDelete();
                }

                $table->index(['web_property_id', 'issue_class', 'captured_at'], 'sc_issue_snapshot_property_issue_captured_idx');
                $table->index(['domain_id', 'captured_at'], 'sc_issue_snapshot_domain_captured_idx');
                $table->index(['capture_method', 'captured_at'], 'sc_issue_snapshot_method_captured_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('search_console_issue_snapshots');
    }
};
