<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('property_repositories', function (Blueprint $table) {
            $table->boolean('is_controller')->default(false)->after('is_primary');
            $table->string('deployment_provider')->nullable()->after('framework');
            $table->string('deployment_project_name')->nullable()->after('deployment_provider');
            $table->string('deployment_project_id')->nullable()->after('deployment_project_name');
            $table->index(['web_property_id', 'is_controller']);
        });

        $property = DB::table('web_properties')
            ->join('web_property_domains', 'web_property_domains.web_property_id', '=', 'web_properties.id')
            ->join('domains', 'domains.id', '=', 'web_property_domains.domain_id')
            ->select('web_properties.id')
            ->where('domains.domain', 'cartransport.movingagain.com.au')
            ->where('web_property_domains.usage_type', 'primary')
            ->first();

        if (! $property || ! is_string($property->id)) {
            return;
        }

        DB::table('property_repositories')
            ->where('web_property_id', $property->id)
            ->update([
                'is_controller' => false,
                'updated_at' => now(),
            ]);

        $existingRepository = DB::table('property_repositories')
            ->select('id')
            ->where('web_property_id', $property->id)
            ->where('repo_name', 'moveroo/ma-catrans-program')
            ->first();

        $attributes = [
            'web_property_id' => $property->id,
            'repo_name' => 'moveroo/ma-catrans-program',
            'repo_provider' => 'github',
            'repo_url' => 'https://github.com/moveroo/ma-catrans-program',
            'local_path' => '/Users/jasonhill/Projects/websites/ma-car-transport-astro',
            'default_branch' => 'main',
            'deployment_branch' => 'main',
            'framework' => 'Astro',
            'deployment_provider' => 'vercel',
            'deployment_project_name' => null,
            'deployment_project_id' => null,
            'is_controller' => true,
            'notes' => 'Authoritative controller repo confirmed from live Vercel deployment status for cartransport.movingagain.com.au.',
            'updated_at' => now(),
        ];

        if ($existingRepository && is_string($existingRepository->id)) {
            DB::table('property_repositories')
                ->where('id', $existingRepository->id)
                ->update($attributes);

            return;
        }

        DB::table('property_repositories')->insert([
            'id' => (string) Str::uuid(),
            ...$attributes,
            'created_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('property_repositories')) {
            return;
        }

        Schema::table('property_repositories', function (Blueprint $table) {
            $table->dropIndex(['web_property_id', 'is_controller']);
            $table->dropColumn([
                'is_controller',
                'deployment_provider',
                'deployment_project_name',
                'deployment_project_id',
            ]);
        });
    }
};
