<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_properties', function (Blueprint $table): void {
            $table->string('canonical_origin_scheme', 16)->nullable()->after('staging_url');
            $table->string('canonical_origin_host', 255)->nullable()->after('canonical_origin_scheme');
            $table->string('canonical_origin_policy', 32)->default('unknown')->after('canonical_origin_host');
            $table->boolean('canonical_origin_enforcement_eligible')->default(false)->after('canonical_origin_policy');
            $table->json('canonical_origin_excluded_subdomains')->nullable()->after('canonical_origin_enforcement_eligible');
            $table->boolean('canonical_origin_sitemap_policy_known')->default(false)->after('canonical_origin_excluded_subdomains');
        });
    }

    public function down(): void
    {
        Schema::table('web_properties', function (Blueprint $table): void {
            $table->dropColumn([
                'canonical_origin_scheme',
                'canonical_origin_host',
                'canonical_origin_policy',
                'canonical_origin_enforcement_eligible',
                'canonical_origin_excluded_subdomains',
                'canonical_origin_sitemap_policy_known',
            ]);
        });
    }
};
