<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CollectSearchConsoleApiBundleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_collects_and_imports_a_search_console_api_bundle_for_a_property(): void
    {
        Storage::fake('local');

        config()->set('services.google.search_console.access_token', 'test-access-token');

        $property = $this->makeProperty('collector-site', 'collector.example.au', '87');

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $property->primaryDomainModel()?->id,
            'web_property_id' => $property->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'capture_method' => 'gsc_drilldown_zip',
            'affected_url_count' => 1,
            'sample_urls' => ['https://collector.example.au/old-page/'],
            'examples' => [
                ['url' => 'https://collector.example.au/old-page/', 'last_crawled' => '2026-03-28'],
            ],
            'normalized_payload' => [
                'affected_urls' => ['https://collector.example.au/old-page/'],
            ],
        ]);

        Http::fake([
            'https://www.googleapis.com/webmasters/v3/sites/*/sitemaps' => Http::response([
                'sitemap' => [
                    [
                        'path' => 'https://collector.example.au/sitemap_index.xml',
                        'warnings' => '0',
                        'errors' => '0',
                    ],
                ],
            ], 200),
            'https://www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'rows' => [
                    [
                        'keys' => ['https://collector.example.au/old-page/'],
                        'clicks' => 3,
                        'impressions' => 40,
                        'ctr' => 0.075,
                        'position' => 12.4,
                    ],
                ],
            ], 200),
            'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect' => Http::response([
                'inspectionResult' => [
                    'indexStatusResult' => [
                        'coverageState' => 'Page with redirect',
                        'robotsTxtState' => 'ALLOWED',
                        'indexingState' => 'INDEXING_ALLOWED',
                        'pageFetchState' => 'SUCCESSFUL',
                        'lastCrawlTime' => '2026-04-01T00:00:00Z',
                        'googleCanonical' => 'https://collector.example.au/new-page/',
                        'userCanonical' => 'https://collector.example.au/old-page/',
                        'referringUrls' => ['https://collector.example.au/sitemap_index.xml'],
                        'sitemap' => ['https://collector.example.au/sitemap_index.xml'],
                    ],
                ],
            ], 200),
        ]);

        $exitCode = Artisan::call('analytics:collect-search-console-api-bundle', [
            'property' => $property->slug,
            '--capture-method' => 'gsc_api',
            '--captured-by' => 'test-suite',
        ]);

        $this->assertSame(0, $exitCode);

        $apiSnapshot = SearchConsoleIssueSnapshot::query()
            ->where('web_property_id', $property->id)
            ->where('capture_method', 'gsc_api')
            ->firstOrFail();

        $this->assertSame('page_with_redirect_in_sitemap', $apiSnapshot->issue_class);
        $this->assertSame('search_console_api_bundle', $apiSnapshot->source_report);
        $this->assertSame('sc-domain:collector.example.au', $apiSnapshot->source_property);
        $this->assertSame(3, data_get($apiSnapshot->normalized_payload, 'search_analytics.totals.clicks'));
        $this->assertSame('https://collector.example.au/sitemap_index.xml', data_get($apiSnapshot->normalized_payload, 'sitemaps.0.path'));
        $this->assertSame(1, data_get($apiSnapshot->normalized_payload, 'url_inspection.summary.coverage_states.Page with redirect'));
        Storage::disk('local')->assertExists($apiSnapshot->artifact_path);
    }

    private function makeProperty(string $slug, string $domainName, string $matomoSiteId): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => $slug,
            'name' => Str::of($slug)->replace('-', ' ')->title()->toString(),
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'production',
            'is_canonical' => true,
        ]);

        $source = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => $matomoSiteId,
            'external_name' => $property->name,
            'is_primary' => true,
            'status' => 'active',
        ]);

        SearchConsoleCoverageStatus::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'source_provider' => 'matomo',
            'matomo_site_id' => $matomoSiteId,
            'matomo_site_name' => $property->name,
            'mapping_state' => 'domain_property',
            'property_uri' => 'sc-domain:'.$domainName,
            'property_type' => 'domain',
            'latest_metric_date' => now()->subDay()->toDateString(),
            'checked_at' => now(),
        ]);

        return $property;
    }
}
