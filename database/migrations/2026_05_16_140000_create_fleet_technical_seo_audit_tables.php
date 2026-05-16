<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_technical_seo_audit_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('web_property_id');
            $table->string('trigger_type', 40)->default('manual');
            $table->unsignedInteger('url_cap')->nullable();
            $table->json('execution_modes');
            $table->string('catalog_version')->nullable();
            $table->string('catalog_checksum', 128)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('summary_counts')->nullable();
            $table->timestamps();

            $table->foreign('web_property_id')->references('id')->on('web_properties')->cascadeOnDelete();
            $table->index(['web_property_id', 'started_at']);
            $table->index('catalog_version');
        });

        Schema::create('fleet_technical_seo_audit_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('fleet_technical_seo_audit_run_id');
            $table->string('check_id', 160);
            $table->string('target_type', 40)->default('web_property');
            $table->text('target_url')->nullable();
            $table->string('result_status', 32);
            $table->string('evidence_confidence', 20);
            $table->json('evidence')->nullable();
            $table->string('owner_system', 80)->nullable();
            $table->uuid('monitoring_finding_id')->nullable();
            $table->string('owner_issue_url', 2048)->nullable();
            $table->timestamps();

            $table->foreign('fleet_technical_seo_audit_run_id', 'fleet_seo_result_run_foreign')
                ->references('id')
                ->on('fleet_technical_seo_audit_runs')
                ->cascadeOnDelete();
            $table->foreign('monitoring_finding_id', 'fleet_seo_result_finding_foreign')
                ->references('id')
                ->on('monitoring_findings')
                ->nullOnDelete();
            $table->index(['fleet_technical_seo_audit_run_id', 'check_id'], 'fleet_seo_result_run_check_index');
            $table->index(['result_status', 'evidence_confidence'], 'fleet_seo_result_status_confidence_index');
            $table->index('monitoring_finding_id', 'fleet_seo_result_finding_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_technical_seo_audit_results');
        Schema::dropIfExists('fleet_technical_seo_audit_runs');
    }
};
