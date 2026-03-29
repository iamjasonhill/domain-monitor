<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncMatomoSearchConsoleCoverageCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_search_console_coverage_from_matomo(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'removalsinterstate.com.au',
            'is_active' => true,
            'platform' => 'WordPress',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'removalsinterstate-com-au',
            'name' => 'Removals Interstate',
            'status' => 'active',
            'property_type' => 'marketing_site',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '29',
            'external_name' => 'Removals Interstate',
            'is_primary' => true,
            'status' => 'active',
        ]);

        config()->set('services.matomo.base_url', 'https://stats.redirection.com.au');
        config()->set('services.matomo.token_auth', 'test-token');

        Http::fake([
            'https://stats.redirection.com.au/index.php*' => Http::response([
                'source_system' => 'matomo_search_console',
                'contract_version' => 1,
                'generated_at' => '2026-03-29T06:00:00Z',
                'sites' => [
                    [
                        'id_site' => '29',
                        'site_name' => 'Removals Interstate',
                        'main_url' => 'https://removalsinterstate.com.au/',
                        'mapping_state' => 'domain_property',
                        'property_uri' => 'sc-domain:removalsinterstate.com.au',
                        'property_type' => 'domain',
                        'mapped_at' => '2026-03-29 05:55:00',
                        'latest_completed_job_at' => '2026-03-29 05:56:00',
                        'latest_completed_job_type' => 'daily',
                        'latest_completed_range_end' => '2026-03-28',
                        'latest_metric_date' => '2026-03-28',
                    ],
                ],
            ], 200),
        ]);

        $exitCode = Artisan::call('analytics:sync-search-console-coverage');

        $this->assertSame(0, $exitCode);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://stats.redirection.com.au/index.php')
                && $request['method'] === 'SearchConsoleIntegration.exportCoverage';
        });

        $status = SearchConsoleCoverageStatus::query()->first();

        $this->assertNotNull($status);
        $this->assertSame($domain->id, $status->domain_id);
        $this->assertSame($property->id, $status->web_property_id);
        $this->assertSame('29', $status->matomo_site_id);
        $this->assertSame('domain_property', $status->mapping_state);
        $this->assertSame('domain', $status->property_type);
        $this->assertSame('sc-domain:removalsinterstate.com.au', $status->property_uri);
    }
}
