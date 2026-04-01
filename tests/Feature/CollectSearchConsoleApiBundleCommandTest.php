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

        config()->set('services.google.search_console.access_token', null);
        config()->set('services.google.search_console.refresh_token', 'test-refresh-token');
        config()->set('services.google.search_console.client_id', 'test-client-id');
        config()->set('services.google.search_console.client_secret', 'test-client-secret');
        config()->set('services.google.search_console.language_code', 'en-US');
        config()->set('services.google.search_console.inspection_request_delay_micros', 0);

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
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'refreshed-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
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
                    'verdict' => 'PASS',
                    'indexStatusResult' => [
                        'coverageState' => 'Page with redirect',
                        'robotsTxtState' => 'ALLOWED',
                        'indexingState' => 'INDEXING_ALLOWED',
                        'pageFetchState' => 'SUCCESSFUL',
                        'lastCrawlTime' => '2026-04-01T00:00:00Z',
                        'crawledAs' => 'MOBILE',
                        'googleCanonical' => 'https://collector.example.au/new-page/',
                        'userCanonical' => 'https://collector.example.au/old-page/',
                        'referringUrls' => ['https://collector.example.au/sitemap_index.xml'],
                        'sitemap' => ['https://collector.example.au/sitemap_index.xml'],
                    ],
                    'richResultsResult' => [
                        'verdict' => 'PASS',
                        'detectedItems' => [
                            [
                                'richResultType' => 'Breadcrumbs',
                                'items' => [
                                    [
                                        'name' => 'BreadcrumbList',
                                    ],
                                ],
                                'issues' => [
                                    [
                                        'issueMessage' => 'Optional field missing',
                                        'severity' => 'WARNING',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'inspectionResultLink' => 'https://search.google.com/search-console/inspect?resource_id=sc-domain:collector.example.au&id=abc123',
                ],
            ], 200),
        ]);

        $exitCode = Artisan::call('analytics:collect-search-console-api-bundle', [
            'property' => $property->slug,
            '--capture-method' => 'gsc_api',
            '--captured-by' => 'test-suite',
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

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
        $this->assertSame('MOBILE', data_get($apiSnapshot->normalized_payload, 'url_inspection.inspected_urls.0.crawled_as'));
        $this->assertSame('https://search.google.com/search-console/inspect?resource_id=sc-domain:collector.example.au&id=abc123', data_get($apiSnapshot->normalized_payload, 'url_inspection.inspected_urls.0.verdict'));
        $this->assertSame('PASS', data_get($apiSnapshot->normalized_payload, 'url_inspection.inspected_urls.0.inspection_verdict'));
        $this->assertSame('https://search.google.com/search-console/inspect?resource_id=sc-domain:collector.example.au&id=abc123', data_get($apiSnapshot->normalized_payload, 'url_inspection.inspected_urls.0.inspection_link'));
        $this->assertSame('PASS', data_get($apiSnapshot->normalized_payload, 'url_inspection.inspected_urls.0.rich_results.verdict'));
        $this->assertSame('Breadcrumbs', data_get($apiSnapshot->normalized_payload, 'url_inspection.inspected_urls.0.rich_results.detected_items.0.rich_result_type'));
        $this->assertSame('BreadcrumbList', data_get($apiSnapshot->normalized_payload, 'url_inspection.inspected_urls.0.rich_results.detected_items.0.items.0.name'));
        $this->assertSame('Optional field missing', data_get($apiSnapshot->normalized_payload, 'url_inspection.inspected_urls.0.rich_results.detected_items.0.issues.0.issue_message'));
        $this->assertSame('WARNING', data_get($apiSnapshot->normalized_payload, 'url_inspection.inspected_urls.0.rich_results.detected_items.0.issues.0.severity'));
        Storage::disk('local')->assertExists($apiSnapshot->artifact_path);

        Http::assertSentCount(4);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://oauth2.googleapis.com/token'
                && $request['grant_type'] === 'refresh_token'
                && $request['refresh_token'] === 'test-refresh-token';
        });
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/webmasters/v3/sites/')
                && str_contains($request->url(), '/sitemaps');
        });
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/webmasters/v3/sites/')
                && str_contains($request->url(), '/searchAnalytics/query');
        });
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect'
                && $request['inspectionUrl'] === 'https://collector.example.au/old-page/'
                && $request['languageCode'] === 'en-US';
        });
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
