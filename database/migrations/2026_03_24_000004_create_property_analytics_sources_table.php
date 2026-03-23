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
        if (! Schema::hasTable('property_analytics_sources')) {
            Schema::create('property_analytics_sources', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('web_property_id');
                $table->string('provider', 30);
                $table->string('external_id');
                $table->string('external_name')->nullable();
                $table->string('workspace_path')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->string('status', 20)->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('web_property_id')->references('id')->on('web_properties')->cascadeOnDelete();
                $table->index(['provider', 'external_id']);
                $table->index(['web_property_id', 'is_primary']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_analytics_sources');
    }
};
