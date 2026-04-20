<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('analytics_event_contracts')) {
            Schema::create('analytics_event_contracts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('key')->unique();
                $table->string('name');
                $table->string('version', 50);
                $table->string('contract_type', 50);
                $table->string('status', 20)->default('active');
                $table->string('scope', 50)->nullable();
                $table->string('source_repo')->nullable();
                $table->string('source_path')->nullable();
                $table->json('contract')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('web_property_event_contracts')) {
            Schema::create('web_property_event_contracts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('web_property_id');
                $table->uuid('analytics_event_contract_id');
                $table->boolean('is_primary')->default(true);
                $table->string('rollout_status', 20)->default('defined');
                $table->timestamp('verified_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('web_property_id')->references('id')->on('web_properties')->cascadeOnDelete();
                $table->foreign('analytics_event_contract_id')->references('id')->on('analytics_event_contracts')->cascadeOnDelete();
                $table->unique(['web_property_id', 'analytics_event_contract_id'], 'web_property_event_contract_unique');
                $table->index(['web_property_id', 'is_primary']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('web_property_event_contracts');
        Schema::dropIfExists('analytics_event_contracts');
    }
};
