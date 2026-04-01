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

class RefreshSearchConsoleApiEnrichmentCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refreshes_missing_and_stale_api_enrichment_for_drilldown_backed_properties(): void
    {
        Storage::fake('local');

        config()->set('services.google.search_console.access_token', null);
        config()->set('services.google.search_console.refresh_token', 'test-refresh-token');
        config()->set('services.google.search_console.client_id', 'test-client-id');
        config()->set('services.google.search_console.client_secret', 'test-client-secret');
        config()->set('services.google.search_console.language_code', 'en-AU');
        config()->set('services.google.search_console.inspection_request_delay_micros', 0);

        $missingApi = $this->makeProperty('missing-api-site', 'missing-api.example.au', '101');
        $staleApi = $this->makeProperty('stale-api-site', 'stale-api.example.au', '102');
        $freshApi = $this->makeProperty('fresh-api-site', 'fresh-api.example.au', '103');

        foreach ([$missingApi, $staleApi, $freshApi] as $property) {
            SearchConsoleIssueSnapshot::factory()->create([
                'domain_id' => $property->primaryDomainModel()?->id,
                'web_property_id' => $property->id,
                'issue_class' => 'page_with_redirect_in_sitemap',
                'capture_method' => 'gsc_drilldown_zip',
                'affected_url_count' => 1,
                'sample_urls' => ['https://'.$property->primaryDomainName().'/old-page/'],
                'examples' => [
                    ['url' => 'https://'.$property->primaryDomainName().'/old-page/', 'last_crawled' => '2026-03-28'],
                ],
                'normalized_payload' => [
                    'affected_urls' => ['https://'.$property->primaryDomainName().'/old-page/'],
                ],
            ]);
        }

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $staleApi->primaryDomainModel()?->id,
            'web_property_id' => $staleApi->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'capture_method' => 'gsc_api',
            'captured_at' => now()->subDays(10),
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $freshApi->primaryDomainModel()?->id,
            'web_property_id' => $freshApi->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'capture_method' => 'gsc_api',
            'captured_at' => now()->subDay(),
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'refreshed-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'https://www.googleapis.com/webmasters/v3/sites/*/sitemaps' => Http::response([
                'sitemap' => [
                    [
                        'path' => 'https://example.au/sitemap_index.xml',
                        'warnings' => '0',
                        'errors' => '0',
                    ],
                ],
            ], 200),
            'https://www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'rows' => [
                    [
                        'keys' => ['https://example.au/old-page/'],
                        'clicks' => 5,
                        'impressions' => 50,
                        'ctr' => 0.1,
                        'position' => 8.2,
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
                        'googleCanonical' => 'https://example.au/new-page/',
                        'userCanonical' => 'https://example.au/old-page/',
                        'referringUrls' => ['https://example.au/sitemap_index.xml'],
                        'sitemap' => ['https://example.au/sitemap_index.xml'],
                    ],
                ],
            ], 200),
        ]);

        $exitCode = Artisan::call('analytics:refresh-search-console-api-enrichment', [
            '--stale-days' => 7,
            '--limit' => 5,
            '--captured-by' => 'test-suite',
        ]);

        $this->assertSame(0, $exitCode);

        $this->assertSame(1, SearchConsoleIssueSnapshot::query()
            ->where('web_property_id', $missingApi->id)
            ->where('capture_method', 'gsc_api')
            ->count());
        $this->assertSame(2, SearchConsoleIssueSnapshot::query()
            ->where('web_property_id', $staleApi->id)
            ->where('capture_method', 'gsc_api')
            ->count());
        $this->assertSame(1, SearchConsoleIssueSnapshot::query()
            ->where('web_property_id', $freshApi->id)
            ->where('capture_method', 'gsc_api')
            ->count());
    }

    public function test_it_lists_candidates_without_importing_in_dry_run_mode(): void
    {
        config()->set('services.google.search_console.access_token', null);
        config()->set('services.google.search_console.refresh_token', 'test-refresh-token');
        config()->set('services.google.search_console.client_id', 'test-client-id');
        config()->set('services.google.search_console.client_secret', 'test-client-secret');
        config()->set('services.google.search_console.inspection_request_delay_micros', 0);

        $property = $this->makeProperty('dry-run-site', 'dry-run.example.au', '104');

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $property->primaryDomainModel()?->id,
            'web_property_id' => $property->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'capture_method' => 'gsc_drilldown_zip',
            'affected_url_count' => 1,
            'sample_urls' => ['https://dry-run.example.au/old-page/'],
            'examples' => [
                ['url' => 'https://dry-run.example.au/old-page/', 'last_crawled' => '2026-03-28'],
            ],
            'normalized_payload' => [
                'affected_urls' => ['https://dry-run.example.au/old-page/'],
            ],
        ]);

        Http::fake();

        $exitCode = Artisan::call('analytics:refresh-search-console-api-enrichment', [
            '--dry-run' => true,
            '--limit' => 5,
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertSame(0, SearchConsoleIssueSnapshot::query()->where('capture_method', 'gsc_api')->count());
        Http::assertNothingSent();
    }

    public function test_it_rejects_an_invalid_capture_method_before_processing_properties(): void
    {
        Http::fake();

        $exitCode = Artisan::call('analytics:refresh-search-console-api-enrichment', [
            '--capture-method' => 'invalid-method',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Invalid --capture-method "invalid-method"', Artisan::output());
        Http::assertNothingSent();
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
