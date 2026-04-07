<?php

namespace Tests\Feature;

use App\Models\DetectedIssueVerification;
use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\DomainSeoBaseline;
use App\Models\DomainTag;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use App\Services\DetectedIssueIdentityService;
use App\Services\DomainHealthCheckRunner;
use App\Services\FleetPropertyContextRefreshService;
use App\Services\PropertyConversionLinkScanner;
use App\Services\SearchConsoleApiEnrichmentRefresher;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery\MockInterface;
use Tests\TestCase;

class FleetPropertyContextRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_property_refresh_command_updates_conversion_link_timestamps(): void
    {
        $property = $this->makeProperty('refresh-links-site', 'refresh-links.example.com');

        Http::fake([
            'https://refresh-links.example.com' => Http::response(<<<'HTML'
                <html>
                    <body>
                        <header>
                            <a href="https://refresh-links.example.com/quote/household">Moving Quote</a>
                        </header>
                    </body>
                </html>
            HTML),
        ]);

        $this->mock(DomainHealthCheckRunner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('run')
                ->times(3)
                ->andReturnUsing(fn (Domain $domain, string $type): array => [
                    'status' => 'skipped',
                    'checked_at' => null,
                    'reason' => "Skipped {$type} in conversion link test.",
                ]);
        });

        $this->mock(SearchConsoleApiEnrichmentRefresher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('refreshProperty')
                ->once()
                ->andReturn([
                    'status' => 'skipped',
                    'captured_at' => null,
                    'reason' => 'missing',
                    'message' => 'No Search Console issue detail evidence is available for enrichment.',
                ]);
        });

        $this->artisan('domains:refresh-fleet-context', [
            '--property' => 'refresh-links-site',
        ])->assertSuccessful();

        $property->refresh();

        $this->assertSame('https://refresh-links.example.com/quote/household', $property->current_household_quote_url);
        $this->assertNotNull($property->conversion_links_scanned_at);
    }

    public function test_property_refresh_command_updates_supported_health_check_results(): void
    {
        $property = $this->makeProperty('refresh-health-site', 'refresh-health.example.com');

        $this->mock(PropertyConversionLinkScanner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('persistForProperty')
                ->once()
                ->andReturn([
                    'current_household_quote_url' => null,
                    'current_household_booking_url' => null,
                    'current_vehicle_quote_url' => null,
                    'current_vehicle_booking_url' => null,
                    'conversion_links_scanned_at' => now(),
                ]);
        });

        $this->mock(SearchConsoleApiEnrichmentRefresher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('refreshProperty')
                ->once()
                ->andReturn([
                    'status' => 'skipped',
                    'captured_at' => null,
                    'reason' => 'missing',
                    'message' => 'No Search Console issue detail evidence is available for enrichment.',
                ]);
        });

        Http::fake([
            'https://refresh-health.example.com' => Http::response(
                '<html><body><a href="/missing-page">Missing page</a></body></html>',
                200,
                [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'Strict-Transport-Security' => 'max-age=600',
                    'X-Frame-Options' => 'SAMEORIGIN',
                    'X-Content-Type-Options' => 'nosniff',
                ]
            ),
            'https://refresh-health.example.com/robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
            'https://refresh-health.example.com/sitemap.xml' => Http::response('', 404),
            'https://refresh-health.example.com/sitemap_index.xml' => Http::response('<xml></xml>', 200),
            'https://refresh-health.example.com/missing-page' => Http::response('', 404),
        ]);

        $this->artisan('domains:refresh-fleet-context', [
            '--property' => 'refresh-health-site',
        ])->assertSuccessful();

        $domainId = $property->primary_domain_id;

        $this->assertSame('fail', DomainCheck::query()->where('domain_id', $domainId)->where('check_type', 'broken_links')->latest('finished_at')->first()?->status);
        $this->assertSame('warn', DomainCheck::query()->where('domain_id', $domainId)->where('check_type', 'security_headers')->latest('finished_at')->first()?->status);
        $this->assertSame('ok', DomainCheck::query()->where('domain_id', $domainId)->where('check_type', 'seo')->latest('finished_at')->first()?->status);
    }

    public function test_search_console_refresh_is_skipped_when_fresh(): void
    {
        Storage::fake('local');

        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');

        $property = $this->makeProperty('fresh-gsc-site', 'fresh-gsc.example.com', withSearchConsoleCoverage: true);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $property->primaryDomainModel()?->id,
            'web_property_id' => $property->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'capture_method' => 'gsc_drilldown_zip',
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $property->primaryDomainModel()?->id,
            'web_property_id' => $property->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'capture_method' => 'gsc_api',
            'captured_at' => now()->subDay(),
        ]);

        $this->mock(PropertyConversionLinkScanner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('persistForProperty')
                ->once()
                ->andReturn([
                    'current_household_quote_url' => null,
                    'current_household_booking_url' => null,
                    'current_vehicle_quote_url' => null,
                    'current_vehicle_booking_url' => null,
                    'conversion_links_scanned_at' => now(),
                ]);
        });

        $this->mock(DomainHealthCheckRunner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('run')
                ->times(3)
                ->andReturn([
                    'status' => 'skipped',
                    'checked_at' => null,
                    'reason' => 'Skipped in Search Console freshness test.',
                ]);
        });

        Http::fake();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer fleet-token',
        ])->postJson('/api/web-properties/fresh-gsc-site/refresh-fleet-context');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('refreshed.search_console_api_enrichment.status', 'skipped')
            ->assertJsonPath('refreshed.search_console_api_enrichment.reason', 'fresh');

        Http::assertNothingSent();
    }

    public function test_search_console_refresh_is_skipped_for_manually_excluded_properties(): void
    {
        Storage::fake('local');

        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');

        $property = $this->makeProperty('excluded-gsc-site', 'excluded-gsc.example.com', withSearchConsoleCoverage: true);

        $manualExclusionTag = DomainTag::firstOrCreate(['name' => 'coverage.excluded']);
        $property->primaryDomainModel()?->tags()->syncWithoutDetaching([$manualExclusionTag->id]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $property->primaryDomainModel()?->id,
            'web_property_id' => $property->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'capture_method' => 'gsc_drilldown_zip',
        ]);

        Http::fake();

        $this->withHeaders([
            'Authorization' => 'Bearer fleet-token',
        ])->postJson('/api/web-properties/excluded-gsc-site/refresh-fleet-context')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('refreshed.conversion_links.status', 'skipped')
            ->assertJsonPath('refreshed.conversion_links.reason', 'primary domain is manually excluded from fleet coverage')
            ->assertJsonPath('refreshed.broken_links.status', 'skipped')
            ->assertJsonPath('refreshed.search_console_api_enrichment.status', 'skipped')
            ->assertJsonPath('refreshed.search_console_api_enrichment.reason', 'ineligible')
            ->assertJsonPath('refreshed.search_console_api_enrichment.message', 'Property is not eligible for Fleet context refresh.');

        Http::assertNothingSent();
    }

    public function test_search_console_refresh_runs_when_stale(): void
    {
        Storage::fake('local');

        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');
        config()->set('services.google.search_console.access_token', null);
        config()->set('services.google.search_console.refresh_token', 'test-refresh-token');
        config()->set('services.google.search_console.client_id', 'test-client-id');
        config()->set('services.google.search_console.client_secret', 'test-client-secret');
        config()->set('services.google.search_console.language_code', 'en-AU');
        config()->set('services.google.search_console.inspection_request_delay_micros', 0);

        $property = $this->makeProperty('stale-gsc-site', 'stale-gsc.example.com', withSearchConsoleCoverage: true);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $property->primaryDomainModel()?->id,
            'web_property_id' => $property->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'capture_method' => 'gsc_drilldown_zip',
            'affected_url_count' => 1,
            'sample_urls' => ['https://stale-gsc.example.com/old-page/'],
            'examples' => [
                ['url' => 'https://stale-gsc.example.com/old-page/', 'last_crawled' => '2026-03-28'],
            ],
            'normalized_payload' => [
                'affected_urls' => ['https://stale-gsc.example.com/old-page/'],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $property->primaryDomainModel()?->id,
            'web_property_id' => $property->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'capture_method' => 'gsc_api',
            'captured_at' => now()->subDays(10),
        ]);

        $this->mock(PropertyConversionLinkScanner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('persistForProperty')
                ->once()
                ->andReturn([
                    'current_household_quote_url' => null,
                    'current_household_booking_url' => null,
                    'current_vehicle_quote_url' => null,
                    'current_vehicle_booking_url' => null,
                    'conversion_links_scanned_at' => now(),
                ]);
        });

        $this->mock(DomainHealthCheckRunner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('run')
                ->times(3)
                ->andReturn([
                    'status' => 'skipped',
                    'checked_at' => null,
                    'reason' => 'Skipped in Search Console stale test.',
                ]);
        });

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'refreshed-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'https://www.googleapis.com/webmasters/v3/sites/*/sitemaps' => Http::response([
                'sitemap' => [
                    [
                        'path' => 'https://stale-gsc.example.com/sitemap_index.xml',
                        'warnings' => '0',
                        'errors' => '0',
                    ],
                ],
            ], 200),
            'https://www.googleapis.com/webmasters/v3/sites/*/searchAnalytics/query' => Http::response([
                'rows' => [
                    [
                        'keys' => ['https://stale-gsc.example.com/old-page/'],
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
                        'googleCanonical' => 'https://stale-gsc.example.com/new-page/',
                        'userCanonical' => 'https://stale-gsc.example.com/old-page/',
                        'referringUrls' => ['https://stale-gsc.example.com/sitemap_index.xml'],
                        'sitemap' => ['https://stale-gsc.example.com/sitemap_index.xml'],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer fleet-token',
        ])->postJson('/api/web-properties/stale-gsc-site/refresh-fleet-context');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('refreshed.search_console_api_enrichment.status', 'refreshed')
            ->assertJsonPath('refreshed.search_console_api_enrichment.reason', 'stale');

        $this->assertSame(
            2,
            SearchConsoleIssueSnapshot::query()
                ->where('web_property_id', $property->id)
                ->where('capture_method', 'gsc_api')
                ->count()
        );
    }

    public function test_active_api_outputs_reflect_refreshed_state_after_endpoint_runs(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');

        $property = $this->makeProperty('api-refresh-site', 'api-refresh.example.com');

        $this->mock(SearchConsoleApiEnrichmentRefresher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('refreshProperty')
                ->once()
                ->andReturn([
                    'status' => 'skipped',
                    'captured_at' => null,
                    'reason' => 'missing',
                    'message' => 'No Search Console issue detail evidence is available for enrichment.',
                ]);
        });

        Http::fake([
            'https://api-refresh.example.com' => Http::response(
                <<<'HTML'
                    <html>
                        <body>
                            <header>
                                <a href="https://api-refresh.example.com/quote/household">Moving Quote</a>
                            </header>
                            <main>
                                <a href="/missing-page">Missing page</a>
                            </main>
                        </body>
                    </html>
                HTML,
                200,
                [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'Strict-Transport-Security' => 'max-age=600',
                    'X-Frame-Options' => 'SAMEORIGIN',
                    'X-Content-Type-Options' => 'nosniff',
                ]
            ),
            'https://api-refresh.example.com/robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
            'https://api-refresh.example.com/sitemap.xml' => Http::response('', 404),
            'https://api-refresh.example.com/sitemap_index.xml' => Http::response('<xml></xml>', 200),
            'https://api-refresh.example.com/missing-page' => Http::response('', 404),
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer fleet-token',
        ])->postJson('/api/web-properties/api-refresh-site/refresh-fleet-context')
            ->assertOk()
            ->assertJsonPath('refreshed.conversion_links.status', 'refreshed')
            ->assertJsonPath('refreshed.broken_links.status', 'refreshed');

        $propertyResponse = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties/api-refresh-site')
            ->assertOk()
            ->assertJsonPath('data.slug', 'api-refresh-site')
            ->assertJsonPath('data.conversion_links.current.household_quote', 'https://api-refresh.example.com/quote/household')
            ->assertJsonPath('data.health_summary.checks.broken_links', 'fail');

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties-summary')
            ->assertOk()
            ->assertJsonPath('web_properties.0.slug', 'api-refresh-site')
            ->assertJsonPath('web_properties.0.conversion_links.current.household_quote', 'https://api-refresh.example.com/quote/household');

        /** @var array<int, array<string, mixed>> $issues */
        $issues = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->json('issues');

        /** @var array<string, mixed>|null $brokenLinksIssue */
        $brokenLinksIssue = collect($issues)->firstWhere('issue_class', 'seo.broken_links');

        $this->assertIsArray($brokenLinksIssue);
        $this->assertSame('api-refresh-site', $brokenLinksIssue['property_slug']);

        $issueDetailResponse = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues/'.urlencode((string) $brokenLinksIssue['issue_id']))
            ->assertOk()
            ->assertJsonPath('issue_class', 'seo.broken_links')
            ->assertJsonPath('property_slug', 'api-refresh-site');

        $this->assertContains(
            'https://api-refresh.example.com/missing-page',
            array_column($issueDetailResponse->json('evidence.broken_links') ?? [], 'url')
        );
    }

    public function test_search_console_refresh_is_skipped_when_current_issue_evidence_is_suppressed(): void
    {
        Storage::fake('local');

        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');

        $property = $this->makeProperty('suppressed-gsc-site', 'suppressed-gsc.example.com', withSearchConsoleCoverage: true);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $property->primaryDomainModel()?->id,
            'web_property_id' => $property->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'capture_method' => 'gsc_drilldown_zip',
        ]);

        $issueId = app(DetectedIssueIdentityService::class)->makeIssueId(
            (string) $property->primaryDomainModel()?->id,
            $property->slug,
            'page_with_redirect_in_sitemap'
        );

        DetectedIssueVerification::create([
            'issue_id' => $issueId,
            'property_slug' => $property->slug,
            'domain' => $property->primaryDomainName(),
            'issue_class' => 'page_with_redirect_in_sitemap',
            'status' => 'verified_fixed_pending_recrawl',
            'hidden_until' => now()->addDay(),
            'verified_at' => now(),
            'evidence' => [
                'captured_at' => now()->subDay()->toIso8601String(),
            ],
        ]);

        $this->mock(PropertyConversionLinkScanner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('persistForProperty')
                ->once()
                ->andReturn([
                    'current_household_quote_url' => null,
                    'current_household_booking_url' => null,
                    'current_vehicle_quote_url' => null,
                    'current_vehicle_booking_url' => null,
                    'conversion_links_scanned_at' => now(),
                ]);
        });

        $this->mock(DomainHealthCheckRunner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('run')
                ->times(3)
                ->andReturnUsing(fn (Domain $domain, string $type): array => $this->skippedHealthResult($type));
        });

        Http::fake();

        $this->withHeaders([
            'Authorization' => 'Bearer fleet-token',
        ])->postJson('/api/web-properties/suppressed-gsc-site/refresh-fleet-context')
            ->assertOk()
            ->assertJsonPath('refreshed.search_console_api_enrichment.status', 'skipped')
            ->assertJsonPath('refreshed.search_console_api_enrichment.reason', 'missing')
            ->assertJsonPath('refreshed.search_console_api_enrichment.message', 'No Search Console issue detail evidence is available for enrichment.');

        Http::assertNothingSent();
    }

    public function test_health_checks_refresh_the_canonical_domain_used_by_api_rollups(): void
    {
        $property = $this->makeProperty('canonical-refresh-site', 'non-canonical.example.com');
        $canonicalDomain = Domain::factory()->create([
            'domain' => 'canonical-refresh.example.com',
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'Vercel',
        ]);

        $property->propertyDomains()->update(['is_canonical' => false]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $canonicalDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $property->refresh()->load('propertyDomains.domain');

        $this->mock(PropertyConversionLinkScanner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('persistForProperty')
                ->once()
                ->andReturn([
                    'current_household_quote_url' => null,
                    'current_household_booking_url' => null,
                    'current_vehicle_quote_url' => null,
                    'current_vehicle_booking_url' => null,
                    'conversion_links_scanned_at' => now(),
                ]);
        });

        $this->mock(SearchConsoleApiEnrichmentRefresher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('refreshProperty')
                ->once()
                ->andReturn([
                    'status' => 'skipped',
                    'captured_at' => null,
                    'reason' => 'missing',
                    'message' => 'No Search Console issue detail evidence is available for enrichment.',
                ]);
        });

        $this->mock(DomainHealthCheckRunner::class, function (MockInterface $mock) use ($canonicalDomain): void {
            $mock->shouldReceive('run')
                ->once()
                ->withArgs(fn (Domain $domain, string $type): bool => $domain->is($canonicalDomain) && $type === 'broken_links')
                ->andReturn($this->skippedHealthResult('broken_links'));
            $mock->shouldReceive('run')
                ->once()
                ->withArgs(fn (Domain $domain, string $type): bool => $domain->is($canonicalDomain) && $type === 'security_headers')
                ->andReturn($this->skippedHealthResult('security_headers'));
            $mock->shouldReceive('run')
                ->once()
                ->withArgs(fn (Domain $domain, string $type): bool => $domain->is($canonicalDomain) && $type === 'seo')
                ->andReturn($this->skippedHealthResult('seo'));
        });

        $this->artisan('domains:refresh-fleet-context', [
            '--property' => 'canonical-refresh-site',
        ])->assertSuccessful();
    }

    public function test_refresh_endpoint_sanitizes_failed_step_errors(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');

        $this->makeProperty('failed-refresh-site', 'failed-refresh.example.com');

        $this->mock(PropertyConversionLinkScanner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('persistForProperty')
                ->once()
                ->andThrow(new \RuntimeException('Scanner failed for https://failed-refresh.example.com/private-path'));
        });

        $this->mock(DomainHealthCheckRunner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('run')
                ->times(3)
                ->andReturnUsing(fn (Domain $domain, string $type): array => $this->skippedHealthResult($type));
        });

        $this->mock(SearchConsoleApiEnrichmentRefresher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('refreshProperty')
                ->once()
                ->andReturn([
                    'status' => 'skipped',
                    'captured_at' => null,
                    'reason' => 'missing',
                    'message' => 'No Search Console issue detail evidence is available for enrichment.',
                ]);
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer fleet-token',
        ])->postJson('/api/web-properties/failed-refresh-site/refresh-fleet-context');

        $response->assertStatus(207)
            ->assertJsonPath('success', false)
            ->assertJsonPath('refreshed.conversion_links.status', 'failed')
            ->assertJsonPath('refreshed.conversion_links.reason', 'conversion_links_refresh_failed');

        $content = (string) $response->getContent();

        $this->assertStringNotContainsString(
            'https://failed-refresh.example.com/private-path',
            $content
        );
    }

    public function test_refresh_runs_live_rechecks_and_prunes_resolved_404_examples_from_active_issues(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');

        $property = $this->makeProperty('recheck-404-site', 'recheck-404.example.com');

        DomainSeoBaseline::create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '555',
            'search_console_property_uri' => 'sc-domain:recheck-404.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'not_found_404' => 2,
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'issue_class' => 'not_found_404',
            'source_issue_label' => 'Not found (404)',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:recheck-404.example.com',
            'captured_at' => now()->subDay(),
            'captured_by' => 'test',
            'affected_url_count' => 2,
            'sample_urls' => [
                'https://recheck-404.example.com/fixed-page/',
                'https://recheck-404.example.com/missing-page/',
            ],
            'examples' => [
                ['url' => 'https://recheck-404.example.com/fixed-page/', 'last_crawled' => now()->subDays(2)->toDateString()],
                ['url' => 'https://recheck-404.example.com/missing-page/', 'last_crawled' => now()->subDays(2)->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://recheck-404.example.com/fixed-page/',
                    'https://recheck-404.example.com/missing-page/',
                ],
            ],
        ]);

        $this->mock(PropertyConversionLinkScanner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('persistForProperty')
                ->once()
                ->andReturn([
                    'current_household_quote_url' => null,
                    'current_household_booking_url' => null,
                    'current_vehicle_quote_url' => null,
                    'current_vehicle_booking_url' => null,
                    'conversion_links_scanned_at' => now(),
                ]);
        });

        $this->mock(DomainHealthCheckRunner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('run')
                ->times(3)
                ->andReturnUsing(fn (Domain $domain, string $type): array => $this->skippedHealthResult($type));
        });

        $this->mock(SearchConsoleApiEnrichmentRefresher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('refreshProperty')
                ->once()
                ->andReturn([
                    'status' => 'skipped',
                    'captured_at' => null,
                    'reason' => 'fresh',
                    'message' => null,
                ]);
        });

        Http::fake([
            'https://recheck-404.example.com/fixed-page/' => Http::response('<html>Fixed</html>', 200),
            'https://recheck-404.example.com/missing-page/' => Http::response('', 404),
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer fleet-token',
        ])->postJson('/api/web-properties/recheck-404-site/refresh-fleet-context')
            ->assertOk()
            ->assertJsonPath('refreshed.search_console_live_rechecks.status', 'refreshed')
            ->assertJsonPath('refreshed.search_console_live_rechecks.checked_url_count', 2);

        $this->assertDatabaseHas('search_console_issue_snapshots', [
            'web_property_id' => $property->id,
            'issue_class' => 'not_found_404',
            'capture_method' => 'gsc_live_recheck',
        ]);

        $issuesResponse = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues');

        $issuesResponse->assertOk()
            ->assertJsonPath('stats.issue_class_counts.not_found_404', 1);

        /** @var array<int, array<string, mixed>> $payloadIssues */
        $payloadIssues = $issuesResponse->json('issues') ?? [];
        $issue = collect($payloadIssues)->firstWhere('issue_class', 'not_found_404');

        $this->assertIsArray($issue);
        $this->assertSame(1, data_get($issue, 'evidence.affected_url_count'));
        $this->assertSame(
            ['https://recheck-404.example.com/missing-page/'],
            data_get($issue, 'evidence.affected_urls')
        );
        $this->assertSame(
            ['https://recheck-404.example.com/missing-page/'],
            collect((array) data_get($issue, 'evidence.live_url_checks'))
                ->pluck('url')
                ->values()
                ->all()
        );
    }

    public function test_refresh_rechecks_subdomain_property_examples_on_other_managed_hosts_and_hides_resolved_404_issue(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('services.domain_monitor.fleet_control_api_key', 'fleet-token');

        $targetProperty = $this->makeProperty('vehicles-backloadingremovals-site', 'vehicles.backloadingremovals.com.au');
        $managedSiblingProperty = $this->makeProperty('removalist-backloadingremovals-site', 'removalist.backloadingremovals.com.au');

        DomainSeoBaseline::create([
            'domain_id' => $targetProperty->primary_domain_id,
            'web_property_id' => $targetProperty->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '556',
            'search_console_property_uri' => 'sc-domain:vehicles.backloadingremovals.com.au',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'not_found_404' => 1,
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $targetProperty->primary_domain_id,
            'web_property_id' => $targetProperty->id,
            'issue_class' => 'not_found_404',
            'source_issue_label' => 'Not found (404)',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:vehicles.backloadingremovals.com.au',
            'captured_at' => now()->subDay(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => [
                'https://removalist.backloadingremovals.com.au/payments',
            ],
            'examples' => [
                ['url' => 'https://removalist.backloadingremovals.com.au/payments', 'last_crawled' => now()->subDays(2)->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://removalist.backloadingremovals.com.au/payments',
                ],
            ],
        ]);

        $this->mock(PropertyConversionLinkScanner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('persistForProperty')
                ->once()
                ->andReturn([
                    'current_household_quote_url' => null,
                    'current_household_booking_url' => null,
                    'current_vehicle_quote_url' => null,
                    'current_vehicle_booking_url' => null,
                    'conversion_links_scanned_at' => now(),
                ]);
        });

        $this->mock(DomainHealthCheckRunner::class, function (MockInterface $mock): void {
            $mock->shouldReceive('run')
                ->times(3)
                ->andReturnUsing(fn (Domain $domain, string $type): array => $this->skippedHealthResult($type));
        });

        $this->mock(SearchConsoleApiEnrichmentRefresher::class, function (MockInterface $mock): void {
            $mock->shouldReceive('refreshProperty')
                ->once()
                ->andReturn([
                    'status' => 'skipped',
                    'captured_at' => null,
                    'reason' => 'fresh',
                    'message' => null,
                ]);
        });

        Http::fake([
            'https://removalist.backloadingremovals.com.au/payments' => Http::response('', 302, [
                'Location' => 'https://removalist.backloadingremovals.com.au/contact',
            ]),
            'https://removalist.backloadingremovals.com.au/contact' => Http::response('<html>Contact</html>', 200),
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer fleet-token',
        ])->postJson('/api/web-properties/vehicles-backloadingremovals-site/refresh-fleet-context')
            ->assertOk()
            ->assertJsonPath('refreshed.search_console_live_rechecks.status', 'refreshed')
            ->assertJsonPath('refreshed.search_console_live_rechecks.checked_url_count', 1);

        $liveRecheck = SearchConsoleIssueSnapshot::query()
            ->where('web_property_id', $targetProperty->id)
            ->where('issue_class', 'not_found_404')
            ->where('capture_method', 'gsc_live_recheck')
            ->latest('captured_at')
            ->first();

        $this->assertNotNull($liveRecheck);
        $this->assertSame(
            'https://removalist.backloadingremovals.com.au/contact',
            data_get($liveRecheck->issueEvidence(), 'live_url_checks.0.final_url')
        );
        $this->assertTrue((bool) data_get($liveRecheck->issueEvidence(), 'live_url_checks.0.resolved_ok'));

        $issuesResponse = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues');

        $issuesResponse->assertOk()
            ->assertJsonMissingPath('stats.issue_class_counts.not_found_404');

        /** @var array<int, array<string, mixed>> $payloadIssues */
        $payloadIssues = $issuesResponse->json('issues') ?? [];
        $issue = collect($payloadIssues)->firstWhere('property_slug', 'vehicles-backloadingremovals-site');

        $this->assertNull($issue);
        $this->assertSame('removalist-backloadingremovals-site', $managedSiblingProperty->slug);
    }

    public function test_refresh_only_updates_the_requested_property_scope(): void
    {
        $targetProperty = $this->makeProperty('target-scope-site', 'target-scope.example.com');
        $otherProperty = $this->makeProperty('other-scope-site', 'other-scope.example.com');
        $otherScannedAt = now()->subDays(3);

        $otherProperty->forceFill([
            'current_household_quote_url' => 'https://other-scope.example.com/existing-quote',
            'conversion_links_scanned_at' => $otherScannedAt,
        ])->save();

        Http::fake([
            'https://target-scope.example.com' => Http::response(<<<'HTML'
                <html>
                    <body>
                        <header>
                            <a href="https://target-scope.example.com/quote/household">Moving Quote</a>
                        </header>
                    </body>
                </html>
            HTML),
            'https://target-scope.example.com/robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
            'https://target-scope.example.com/sitemap.xml' => Http::response('<xml></xml>', 200),
            'https://target-scope.example.com/sitemap_index.xml' => Http::response('<xml></xml>', 200),
        ]);

        $this->mock(SearchConsoleApiEnrichmentRefresher::class, function (MockInterface $mock) use ($targetProperty): void {
            $mock->shouldReceive('refreshProperty')
                ->once()
                ->withArgs(fn (WebProperty $property): bool => $property->is($targetProperty))
                ->andReturn([
                    'status' => 'skipped',
                    'captured_at' => null,
                    'reason' => 'missing',
                    'message' => 'No Search Console issue detail evidence is available for enrichment.',
                ]);
        });

        $this->artisan('domains:refresh-fleet-context', [
            '--property' => 'target-scope-site',
        ])->assertSuccessful();

        $targetProperty->refresh();
        $otherProperty->refresh();

        $this->assertSame('https://target-scope.example.com/quote/household', $targetProperty->current_household_quote_url);
        $this->assertNotNull($targetProperty->conversion_links_scanned_at);
        $this->assertSame('https://other-scope.example.com/existing-quote', $otherProperty->current_household_quote_url);
        $this->assertSame(
            $otherScannedAt->toDateTimeString(),
            $otherProperty->conversion_links_scanned_at?->toDateTimeString()
        );
        $this->assertSame(0, DomainCheck::query()->where('domain_id', $otherProperty->primary_domain_id)->count());
    }

    public function test_refresh_command_rejects_invalid_stale_day_override(): void
    {
        $this->mock(FleetPropertyContextRefreshService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('refresh');
        });

        $this->artisan('domains:refresh-fleet-context', [
            '--property' => 'refresh-links-site',
            '--stale-days' => 'abc',
        ])
            ->expectsOutputToContain('The --stale-days option must be an integer between 1 and 30.')
            ->assertExitCode(Command::INVALID);
    }

    /**
     * @return array{status: string, checked_at: string|null, reason: string|null}
     */
    private function skippedHealthResult(string $type): array
    {
        return [
            'status' => 'skipped',
            'checked_at' => null,
            'reason' => "Skipped {$type}.",
        ];
    }

    private function makeProperty(string $slug, string $domainName, bool $withSearchConsoleCoverage = false): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'expires_at' => null,
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'Vercel',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => $slug,
            'name' => Str::of($slug)->replace('-', ' ')->title()->toString(),
            'status' => 'active',
            'property_type' => 'website',
            'production_url' => 'https://'.$domainName,
            'primary_domain_id' => $domain->id,
            'target_household_quote_url' => 'https://'.$domainName.'/target-household',
            'target_moveroo_subdomain_url' => 'https://'.$slug.'.moveroo.com.au',
            'target_contact_us_page_url' => 'https://'.$domainName.'/contact-us',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => '_wp-house',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
            'framework' => 'WordPress',
            'is_primary' => true,
            'is_controller' => true,
        ]);

        if ($withSearchConsoleCoverage) {
            $source = PropertyAnalyticsSource::create([
                'web_property_id' => $property->id,
                'provider' => 'matomo',
                'external_id' => '999',
                'external_name' => $property->name,
                'is_primary' => true,
                'status' => 'active',
            ]);

            SearchConsoleCoverageStatus::create([
                'domain_id' => $domain->id,
                'web_property_id' => $property->id,
                'property_analytics_source_id' => $source->id,
                'source_provider' => 'matomo',
                'matomo_site_id' => '999',
                'matomo_site_name' => $property->name,
                'mapping_state' => 'domain_property',
                'property_uri' => 'sc-domain:'.$domainName,
                'property_type' => 'domain',
                'latest_metric_date' => now()->subDay()->toDateString(),
                'checked_at' => now(),
            ]);
        }

        return $property;
    }
}
