<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_technical_seo_unknown_triage_candidates', function (Blueprint $table): void {
            $table->id();
            $table->string('dedupe_key', 255)->unique();
            $table->uuid('web_property_id');
            $table->uuid('domain_id')->nullable();
            $table->string('property_slug');
            $table->string('audit_profile', 80);
            $table->string('coverage_unit', 2048);
            $table->string('check_id', 160);
            $table->string('owner_route', 40);
            $table->uuid('latest_audit_run_id');
            $table->uuid('latest_audit_result_id');
            $table->unsignedInteger('retry_count');
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('status', 40)->default('open');
            $table->json('candidate_payload');
            $table->timestamps();

            $table->foreign('web_property_id')->references('id')->on('web_properties')->cascadeOnDelete();
            $table->foreign('domain_id')->references('id')->on('domains')->nullOnDelete();
            $table->foreign('latest_audit_run_id')->references('id')->on('fleet_technical_seo_audit_runs')->cascadeOnDelete();
            $table->foreign('latest_audit_result_id')->references('id')->on('fleet_technical_seo_audit_results')->cascadeOnDelete();
            $table->index(['status', 'owner_route']);
            $table->index(['audit_profile', 'check_id']);
            $table->index(['web_property_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_technical_seo_unknown_triage_candidates');
    }
};
