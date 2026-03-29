<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainSeoBaseline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncMatomoSearchConsoleBaselineCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exports_from_matomo_and_imports_a_domain_baseline(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'removalsinterstate.com.au',
        ]);

        config()->set('services.matomo.base_url', 'https://stats.redirection.com.au');
        config()->set('services.matomo.token_auth', 'test-token');
        config()->set('domain_monitor.web_property_bootstrap.overrides', [
            'removalsinterstate.com.au' => [
                'slug' => 'removalsinterstate-com-au',
                'name' => 'removalsinterstate.com.au',
                'property_type' => 'website',
                'analytics_sources' => [
                    [
                        'provider' => 'matomo',
                        'external_id' => '29',
                        'external_name' => 'Removals Interstate',
                    ],
                ],
            ],
        ]);

        Http::fake([
            'https://stats.redirection.com.au/index.php*' => Http::response([
                'domain' => 'removalsinterstate.com.au',
                'baseline_type' => 'manual_checkpoint',
                'captured_at' => '2026-03-29T04:00:00Z',
                'captured_by' => 'domain-monitor',
                'source_provider' => 'matomo',
                'matomo_site_id' => '29',
                'search_console_property_uri' => 'https://removalsinterstate.com.au/',
                'search_type' => 'web',
                'date_range_start' => '2025-12-29',
                'date_range_end' => '2026-03-28',
                'import_method' => 'matomo_plus_manual_csv',
                'artifact_path' => 'domain-monitor/search-console-baselines/removalsinterstate-com-au/2026-03-29/',
                'clicks' => 6,
                'impressions' => 5370,
                'ctr' => 0.001117,
                'average_position' => 52.378585,
                'indexed_pages' => 28,
                'not_indexed_pages' => 199,
                'inspected_url_count' => 25,
                'inspection_indexed_url_count' => 12,
                'inspection_non_indexed_url_count' => 13,
                'raw_payload' => [
                    'search_type_totals' => [
                        [
                            'search_type' => 'web',
                            'clicks' => 6,
                            'impressions' => 5370,
                        ],
                    ],
                ],
            ], 200),
        ]);

        $exitCode = Artisan::call('analytics:sync-search-console-baseline', [
            '--domain' => $domain->domain,
            '--start' => '2025-12-29',
            '--end' => '2026-03-28',
        ]);

        $this->assertSame(0, $exitCode);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://stats.redirection.com.au/index.php')
                && $request['method'] === 'SearchConsoleIntegration.exportBaseline'
                && $request['idSite'] === '29'
                && $request['startDate'] === '2025-12-29'
                && $request['endDate'] === '2026-03-28';
        });

        $baseline = DomainSeoBaseline::query()->first();

        $this->assertNotNull($baseline);
        $this->assertSame($domain->id, $baseline->domain_id);
        $this->assertSame('29', $baseline->matomo_site_id);
        $this->assertSame(5370.0, (float) $baseline->impressions);
        $this->assertSame(28, $baseline->indexed_pages);
        $this->assertSame(25, $baseline->inspected_url_count);
    }
}
