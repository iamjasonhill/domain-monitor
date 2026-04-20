<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('property_analytics_sources')) {
            return;
        }

        Schema::table('property_analytics_sources', function (Blueprint $table) {
            if (! Schema::hasColumn('property_analytics_sources', 'provider_config')) {
                $table->json('provider_config')->nullable()->after('workspace_path');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('property_analytics_sources')) {
            return;
        }

        Schema::table('property_analytics_sources', function (Blueprint $table) {
            if (Schema::hasColumn('property_analytics_sources', 'provider_config')) {
                $table->dropColumn('provider_config');
            }
        });
    }
};
