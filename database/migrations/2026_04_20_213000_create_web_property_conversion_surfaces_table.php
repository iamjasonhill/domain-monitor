<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('web_property_conversion_surfaces')) {
            return;
        }

        Schema::create('web_property_conversion_surfaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('web_property_id');
            $table->uuid('domain_id')->nullable();
            $table->uuid('property_analytics_source_id')->nullable();
            $table->uuid('web_property_event_contract_id')->nullable();
            $table->string('hostname')->unique();
            $table->string('surface_type', 50)->default('quote_subdomain');
            $table->string('journey_type', 50)->nullable();
            $table->string('runtime_driver', 50)->nullable();
            $table->string('runtime_label')->nullable();
            $table->string('runtime_path')->nullable();
            $table->string('tenant_key', 100)->nullable();
            $table->string('analytics_binding_mode', 30)->default('inherits_property');
            $table->string('event_contract_binding_mode', 30)->default('inherits_property');
            $table->string('rollout_status', 20)->default('defined');
            $table->timestamp('verified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('web_property_id')->references('id')->on('web_properties')->cascadeOnDelete();
            $table->foreign('domain_id')->references('id')->on('domains')->nullOnDelete();
            $table->foreign('property_analytics_source_id')->references('id')->on('property_analytics_sources')->nullOnDelete();
            $table->foreign('web_property_event_contract_id')->references('id')->on('web_property_event_contracts')->nullOnDelete();
            $table->index(['web_property_id', 'surface_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('web_property_conversion_surfaces');
    }
};
