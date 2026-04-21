<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\MonitoringFinding;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Models\WebPropertyConversionSurface;
use App\Models\WebPropertyDomain;
use App\Services\BrokenLinkHealthCheck;
use App\Services\ExternalLinkInventoryHealthCheck;
use App\Services\HttpHealthCheck;
use App\Services\PropertySiteSignalScanner;
use App\Services\SslHealthCheck;
use App\Services\UptimeHealthCheck;
use Brain\Client\BrainEventClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class RunMonitoringLaneCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_integrity_lane_creates_and_surfaces_a_ga4_install_finding(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $property = $this->makeProperty('tracked.example.au', 'Tracked Example');
        PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'ga4',
            'external_id' => 'G-EXPECTED123',
            'external_name' => 'Tracked Example',
            'provider_config' => [
                'measurement_id' => 'G-EXPECTED123',
            ],
            'is_primary' => true,
            'status' => 'active',
        ]);

        Http::fake([
            'https://tracked.example.au/' => Http::response($this->homepageHtml(canonical: 'https://tracked.example.au/'), 200),
            'https://tracked.example.au/robots.txt' => Http::response("User-agent: *\nAllow: /\nSitemap: https://tracked.example.au/sitemap.xml\n", 200),
            'https://tracked.example.au/sitemap.xml' => Http::response('<urlset></urlset>', 200),
        ]);

        $brain = Mockery::mock(BrainEventClient::class);
        $this->instance(BrainEventClient::class, $brain);
        /** @var Mockery\Expectation $sendAsyncExpectation */
        $sendAsyncExpectation = $brain->shouldReceive('sendAsync');
        $sendAsyncExpectation->once()->withArgs(function (string $eventType, array $payload) use ($property): bool {
            $this->assertSame('domain_monitor.finding.opened', $eventType);
            $this->assertSame('marketing.ga4_install', $payload['finding_type']);
            $this->assertSame($property->slug, data_get($payload, 'web_property.slug'));
            $this->assertSame('regression', $payload['issue_type']);
            $this->assertSame('open', $payload['finding_status']);

            return true;
        });

        $this->assertSame(0, Artisan::call('monitoring:run-lane', [
            'lane' => 'marketing_integrity',
            '--property' => $property->slug,
        ]));

        $finding = MonitoringFinding::query()->where('finding_type', 'marketing.ga4_install')->firstOrFail();

        $this->assertSame(MonitoringFinding::STATUS_OPEN, $finding->status);
        $this->assertSame($property->id, $finding->web_property_id);
        $this->assertSame('missing_ga4', data_get($finding->evidence, 'verdict'));

        $issues = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->json('issues');

        $this->assertIsArray($issues);
        /** @var array<int, array<string, mixed>> $issues */
        $matchingIssue = collect($issues)->firstWhere('issue_id', $finding->issue_id);

        $this->assertIsArray($matchingIssue);
        $this->assertSame('marketing.ga4_install', $matchingIssue['issue_class']);
        $this->assertSame('must_fix', $matchingIssue['severity']);
    }

    public function test_marketing_integrity_lane_recovers_existing_findings_and_flags_conversion_surface_mismatches(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');

        $property = $this->makeProperty('brand.example.au', 'Brand Example');
        $source = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'ga4',
            'external_id' => 'G-EXPECTED999',
            'external_name' => 'Brand Example',
            'provider_config' => [
                'measurement_id' => 'G-EXPECTED999',
            ],
            'is_primary' => true,
            'status' => 'active',
        ]);

        $surfaceDomain = Domain::factory()->create([
            'domain' => 'quotes.brand.example.au',
            'is_active' => true,
            'platform' => 'Laravel',
        ]);

        WebPropertyConversionSurface::create([
            'web_property_id' => $property->id,
            'domain_id' => $surfaceDomain->id,
            'property_analytics_source_id' => $source->id,
            'hostname' => 'quotes.brand.example.au',
            'surface_type' => 'quote',
            'journey_type' => 'household',
            'analytics_binding_mode' => 'inherits_property',
            'event_contract_binding_mode' => 'inherits_property',
            'rollout_status' => 'instrumented',
        ]);

        $brain = Mockery::mock(BrainEventClient::class);
        $this->instance(BrainEventClient::class, $brain);
        /** @var Mockery\Expectation $recoveredExpectation */
        $recoveredExpectation = $brain->shouldReceive('sendAsync');
        $recoveredExpectation->once()->withArgs(function (string $eventType, array $payload): bool {
            $this->assertSame('domain_monitor.finding.recovered', $eventType);
            $this->assertSame('marketing.ga4_install', $payload['finding_type']);
            $this->assertSame('recovered', $payload['finding_status']);

            return true;
        });
        /** @var Mockery\Expectation $openedExpectation */
        $openedExpectation = $brain->shouldReceive('sendAsync');
        $openedExpectation->once()->withArgs(function (string $eventType, array $payload): bool {
            $this->assertSame('domain_monitor.finding.opened', $eventType);
            $this->assertSame('marketing.conversion_surface_ga4', $payload['finding_type']);

            return true;
        });

        $primaryDomain = $property->primaryDomainModel();
        $this->assertInstanceOf(Domain::class, $primaryDomain);

        MonitoringFinding::factory()->create([
            'issue_id' => app(\App\Services\DetectedIssueIdentityService::class)->makeIssueId(
                $primaryDomain->id,
                $property->slug,
                'marketing.ga4_install'
            ),
            'lane' => 'marketing_integrity',
            'finding_type' => 'marketing.ga4_install',
            'issue_type' => 'regression',
            'scope_type' => 'web_property',
            'domain_id' => $primaryDomain->id,
            'web_property_id' => $property->id,
            'status' => MonitoringFinding::STATUS_OPEN,
            'title' => 'GA4 install mismatch on live property',
            'summary' => 'Old failure',
            'first_detected_at' => now()->subDay(),
            'last_detected_at' => now()->subDay(),
            'evidence' => ['verdict' => 'missing_ga4'],
        ]);

        Http::fake([
            'https://brand.example.au/' => Http::response($this->homepageHtml(
                canonical: 'https://brand.example.au/',
                measurementId: 'G-EXPECTED999'
            ), 200),
            'https://brand.example.au/robots.txt' => Http::response("User-agent: *\nAllow: /\nSitemap: https://brand.example.au/sitemap.xml\n", 200),
            'https://brand.example.au/sitemap.xml' => Http::response('<urlset></urlset>', 200),
            'https://quotes.brand.example.au/' => Http::response(
                $this->homepageHtml(
                    canonical: 'https://quotes.brand.example.au/',
                    measurementId: 'G-WRONG000'
                ),
                200
            ),
        ]);

        $this->assertSame(0, Artisan::call('monitoring:run-lane', [
            'lane' => 'marketing_integrity',
            '--property' => $property->slug,
        ]));

        $this->assertDatabaseHas('monitoring_findings', [
            'web_property_id' => $property->id,
            'finding_type' => 'marketing.ga4_install',
            'status' => MonitoringFinding::STATUS_RECOVERED,
        ]);

        $conversionFinding = MonitoringFinding::query()
            ->where('web_property_id', $property->id)
            ->where('finding_type', 'marketing.conversion_surface_ga4')
            ->firstOrFail();

        $this->assertSame(MonitoringFinding::STATUS_OPEN, $conversionFinding->status);
        $this->assertSame('wrong_measurement_id', data_get($conversionFinding->evidence, 'verdict'));
        $this->assertSame('quotes.brand.example.au', data_get($conversionFinding->evidence, 'failing_surfaces.0.hostname'));
    }

    public function test_marketing_integrity_lane_flags_properties_without_an_active_primary_ga4_source(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');

        $property = $this->makeProperty('inactive-primary.example.au', 'Inactive Primary');
        $primarySource = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'ga4',
            'external_id' => 'G-INACTIVE001',
            'external_name' => 'Inactive Primary',
            'provider_config' => [
                'measurement_id' => 'G-INACTIVE001',
            ],
            'is_primary' => true,
            'status' => 'inactive',
        ]);

        PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'ga4',
            'external_id' => 'G-ACTIVE999',
            'external_name' => 'Inactive Primary Secondary',
            'provider_config' => [
                'measurement_id' => 'G-ACTIVE999',
            ],
            'is_primary' => false,
            'status' => 'active',
        ]);

        $primaryDomain = $property->primaryDomainModel();
        $this->assertInstanceOf(Domain::class, $primaryDomain);

        MonitoringFinding::factory()->create([
            'issue_id' => app(\App\Services\DetectedIssueIdentityService::class)->makeIssueId(
                $primaryDomain->id,
                $property->slug,
                'marketing.ga4_install'
            ),
            'lane' => 'marketing_integrity',
            'finding_type' => 'marketing.ga4_install',
            'issue_type' => 'regression',
            'scope_type' => 'web_property',
            'domain_id' => $primaryDomain->id,
            'web_property_id' => $property->id,
            'status' => MonitoringFinding::STATUS_OPEN,
            'title' => 'GA4 install mismatch on live property',
            'summary' => 'Existing open finding',
            'evidence' => ['verdict' => 'missing_ga4'],
        ]);

        Http::fake([
            'https://inactive-primary.example.au/' => Http::response(
                "<html><head><link rel=\"canonical\" href=\"https://inactive-primary.example.au/\" /><script async src=\"https://www.googletagmanager.com/gtag/js?id=G-ACTIVE999\"></script><script>gtag('config','G-ACTIVE999');</script></head><body>Body</body></html>",
                200
            ),
            'https://inactive-primary.example.au/robots.txt' => Http::response("User-agent: *\nAllow: /\nSitemap: https://inactive-primary.example.au/sitemap.xml\n", 200),
            'https://inactive-primary.example.au/sitemap.xml' => Http::response('<urlset></urlset>', 200),
        ]);

        $brain = Mockery::mock(BrainEventClient::class);
        $this->instance(BrainEventClient::class, $brain);
        /** @var Mockery\Expectation $updatedExpectation */
        $updatedExpectation = $brain->shouldReceive('sendAsync');
        $updatedExpectation->once()->withArgs(function (string $eventType, array $payload): bool {
            $this->assertSame('domain_monitor.finding.updated', $eventType);
            $this->assertSame('marketing.ga4_install', $payload['finding_type']);
            $this->assertSame('regression', $payload['issue_type']);
            $this->assertSame('missing_expected_measurement_id', data_get($payload, 'evidence.verdict'));
            $this->assertSame(['G-ACTIVE999'], data_get($payload, 'evidence.detected_measurement_ids'));

            return true;
        });

        $this->assertSame(0, Artisan::call('monitoring:run-lane', [
            'lane' => 'marketing_integrity',
            '--property' => $property->slug,
        ]));

        $this->assertDatabaseHas('monitoring_findings', [
            'issue_id' => app(\App\Services\DetectedIssueIdentityService::class)->makeIssueId(
                $primaryDomain->id,
                $property->slug,
                'marketing.ga4_install'
            ),
            'status' => MonitoringFinding::STATUS_OPEN,
            'summary' => 'Property does not have an active GA4 measurement ID configured in domain-monitor.',
        ]);

        $finding = MonitoringFinding::query()
            ->where('web_property_id', $property->id)
            ->where('finding_type', 'marketing.ga4_install')
            ->firstOrFail();

        $this->assertSame('missing_expected_measurement_id', data_get($finding->evidence, 'verdict'));
        $this->assertSame(['G-ACTIVE999'], data_get($finding->evidence, 'detected_measurement_ids'));

        $this->assertDatabaseHas('property_analytics_sources', [
            'id' => $primarySource->id,
            'status' => 'inactive',
            'is_primary' => true,
        ]);
    }

    public function test_marketing_integrity_lane_reports_indexability_failures(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');

        $property = $this->makeProperty('indexability.example.au', 'Indexability Example');
        PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'ga4',
            'external_id' => 'G-INDEX001',
            'external_name' => 'Indexability Example',
            'provider_config' => [
                'measurement_id' => 'G-INDEX001',
            ],
            'is_primary' => true,
            'status' => 'active',
        ]);

        Http::fake([
            'https://indexability.example.au/' => Http::response(
                $this->homepageHtml(measurementId: 'G-INDEX001', metaRobots: 'noindex,follow'),
                200
            ),
            'https://indexability.example.au/robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
        ]);

        $brain = Mockery::mock(BrainEventClient::class);
        $this->instance(BrainEventClient::class, $brain);
        /** @var Mockery\Expectation $indexabilityExpectation */
        $indexabilityExpectation = $brain->shouldReceive('sendAsync');
        $indexabilityExpectation->once()->withArgs(function (string $eventType, array $payload): bool {
            $this->assertSame('domain_monitor.finding.opened', $eventType);
            $this->assertSame('marketing.indexability', $payload['finding_type']);

            return true;
        });

        $this->assertSame(0, Artisan::call('monitoring:run-lane', [
            'lane' => 'marketing_integrity',
            '--property' => $property->slug,
        ]));

        $finding = MonitoringFinding::query()
            ->where('web_property_id', $property->id)
            ->where('finding_type', 'marketing.indexability')
            ->firstOrFail();

        $this->assertSame(MonitoringFinding::STATUS_OPEN, $finding->status);
        $this->assertSame('missing_canonical', data_get($finding->evidence, 'verdict'));
        $this->assertSame(['missing_canonical', 'homepage_noindex', 'sitemap_not_referenced'], data_get($finding->evidence, 'problems'));
    }

    public function test_marketing_integrity_lane_reports_quote_handoff_mismatches_without_requiring_ga4(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');

        $property = $this->makeProperty('handoff.example.au', 'Handoff Example');
        $property->forceFill([
            'target_household_quote_url' => 'https://quotes.handoff.example.au/quote/household',
            'target_vehicle_quote_url' => 'https://quotes.handoff.example.au/quote/vehicle',
        ])->save();

        Http::fake([
            'https://handoff.example.au' => Http::response($this->homepageHtml(
                canonical: 'https://handoff.example.au/',
                body: '<nav><a href="https://legacy.example.au/start-quote">Moving quote</a><a href="https://quotes.handoff.example.au/quote/vehicle">Car quote</a></nav>'
            ), 200),
            'https://handoff.example.au/' => Http::response($this->homepageHtml(
                canonical: 'https://handoff.example.au/',
                body: '<nav><a href="https://legacy.example.au/start-quote">Moving quote</a><a href="https://quotes.handoff.example.au/quote/vehicle">Car quote</a></nav>'
            ), 200),
            'https://handoff.example.au/robots.txt' => Http::response("User-agent: *\nAllow: /\nSitemap: https://handoff.example.au/sitemap.xml\n", 200),
            'https://handoff.example.au/sitemap.xml' => Http::response('<urlset></urlset>', 200),
        ]);

        $brain = Mockery::mock(BrainEventClient::class);
        $this->instance(BrainEventClient::class, $brain);
        /** @var Mockery\Expectation $ga4Expectation */
        $ga4Expectation = $brain->shouldReceive('sendAsync');
        $ga4Expectation->once()->withArgs(function (string $eventType, array $payload): bool {
            $this->assertSame('domain_monitor.finding.opened', $eventType);
            $this->assertSame('marketing.ga4_install', $payload['finding_type']);
            $this->assertSame('missing_expected_measurement_id', data_get($payload, 'evidence.verdict'));

            return true;
        });
        /** @var Mockery\Expectation $quoteHandoffExpectation */
        $quoteHandoffExpectation = $brain->shouldReceive('sendAsync');
        $quoteHandoffExpectation->once()->withArgs(function (string $eventType, array $payload): bool {
            $this->assertSame('domain_monitor.finding.opened', $eventType);
            $this->assertSame('marketing.quote_handoff_integrity', $payload['finding_type']);
            $this->assertSame('regression', $payload['issue_type']);

            return true;
        });

        $this->assertSame(0, Artisan::call('monitoring:run-lane', [
            'lane' => 'marketing_integrity',
            '--property' => $property->slug,
        ]));

        $finding = MonitoringFinding::query()
            ->where('web_property_id', $property->id)
            ->where('finding_type', 'marketing.quote_handoff_integrity')
            ->firstOrFail();

        $this->assertSame(MonitoringFinding::STATUS_OPEN, $finding->status);
        $this->assertSame('wrong_handoff_target', data_get($finding->evidence, 'verdict'));
        $this->assertSame('household_quote', data_get($finding->evidence, 'mismatches.0.slot'));
        $this->assertDatabaseHas('monitoring_findings', [
            'web_property_id' => $property->id,
            'finding_type' => 'marketing.ga4_install',
            'status' => MonitoringFinding::STATUS_OPEN,
        ]);
    }

    public function test_marketing_integrity_lane_does_not_run_quote_handoff_for_moveroo_subdomain_only_targets(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');

        $property = $this->makeProperty('subdomain-only.example.au', 'Subdomain Only');
        $property->forceFill([
            'target_moveroo_subdomain_url' => 'https://quotes.subdomain-only.example.au',
        ])->save();

        Http::fake([
            'https://subdomain-only.example.au/' => Http::response($this->homepageHtml(
                canonical: 'https://subdomain-only.example.au/'
            ), 200),
            'https://subdomain-only.example.au/robots.txt' => Http::response("User-agent: *\nAllow: /\nSitemap: https://subdomain-only.example.au/sitemap.xml\n", 200),
            'https://subdomain-only.example.au/sitemap.xml' => Http::response('<urlset></urlset>', 200),
        ]);

        $brain = Mockery::mock(BrainEventClient::class);
        $this->instance(BrainEventClient::class, $brain);
        /** @var Mockery\Expectation $ga4Expectation */
        $ga4Expectation = $brain->shouldReceive('sendAsync');
        $ga4Expectation->once()->withArgs(function (string $eventType, array $payload): bool {
            $this->assertSame('domain_monitor.finding.opened', $eventType);
            $this->assertSame('marketing.ga4_install', $payload['finding_type']);
            $this->assertSame('missing_expected_measurement_id', data_get($payload, 'evidence.verdict'));

            return true;
        });

        $this->assertSame(0, Artisan::call('monitoring:run-lane', [
            'lane' => 'marketing_integrity',
            '--property' => $property->slug,
        ]));

        $this->assertDatabaseHas('monitoring_findings', [
            'web_property_id' => $property->id,
            'finding_type' => 'marketing.ga4_install',
            'status' => MonitoringFinding::STATUS_OPEN,
        ]);
        $this->assertNull(MonitoringFinding::query()
            ->where('web_property_id', $property->id)
            ->where('finding_type', 'marketing.quote_handoff_integrity')
            ->first());
    }

    public function test_seo_agent_readiness_lane_reports_missing_structured_data_and_agent_files(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $property = $this->makeProperty('readiness.example.au', 'Readiness Example');

        Http::fake([
            'https://readiness.example.au/' => Http::response($this->homepageHtml(canonical: 'https://readiness.example.au/'), 200),
            'https://readiness.example.au/robots.txt' => Http::response("User-agent: *\nAllow: /\n", 200),
            'https://readiness.example.au/sitemap.xml' => Http::response('<urlset></urlset>', 200),
            'https://readiness.example.au/llms.txt' => Http::response('missing', 404),
        ]);

        $brain = Mockery::mock(BrainEventClient::class);
        $this->instance(BrainEventClient::class, $brain);
        /** @var Mockery\Expectation $seoReadinessExpectation */
        $seoReadinessExpectation = $brain->shouldReceive('sendAsync');
        $seoReadinessExpectation->twice()->withArgs(
            fn (string $eventType, array $payload): bool => $eventType === 'domain_monitor.finding.opened'
                && in_array($payload['finding_type'], ['seo.structured_data', 'seo.agent_readiness'], true)
        );

        $this->assertSame(0, Artisan::call('monitoring:run-lane', [
            'lane' => 'seo_agent_readiness',
            '--property' => $property->slug,
        ]));

        $structuredDataFinding = MonitoringFinding::query()
            ->where('web_property_id', $property->id)
            ->where('finding_type', 'seo.structured_data')
            ->firstOrFail();

        $agentFinding = MonitoringFinding::query()
            ->where('web_property_id', $property->id)
            ->where('finding_type', 'seo.agent_readiness')
            ->firstOrFail();

        $this->assertSame(MonitoringFinding::STATUS_OPEN, $structuredDataFinding->status);
        $this->assertSame(MonitoringFinding::STATUS_OPEN, $agentFinding->status);
        $this->assertSame(['llms.txt'], data_get($agentFinding->evidence, 'missing_files'));

        $issues = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->json('issues');

        $this->assertIsArray($issues);
        /** @var array<int, array<string, mixed>> $issues */
        $matchingIssue = collect($issues)->firstWhere('issue_id', $structuredDataFinding->issue_id);

        $this->assertIsArray($matchingIssue);
        $this->assertSame('should_fix', $matchingIssue['severity']);
    }

    public function test_critical_live_lane_reports_redirect_policy_mismatches(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');

        $property = $this->makeProperty('redirect.example.au', 'Redirect Example');
        $property->forceFill([
            'canonical_origin_scheme' => 'https',
            'canonical_origin_host' => 'redirect.example.au',
            'canonical_origin_policy' => 'known',
        ])->save();

        $this->mockCriticalLiveHealthChecks(
            uptime: [
                'is_valid' => true,
                'status_code' => 200,
                'duration_ms' => 10,
                'error_message' => null,
                'payload' => ['url' => 'https://redirect.example.au', 'status_code' => 200, 'duration_ms' => 10],
            ],
            http: [
                'status_code' => 200,
                'duration_ms' => 11,
                'is_up' => true,
                'error_message' => null,
                'payload' => ['url' => 'https://redirect.example.au', 'headers' => [], 'redirected' => false],
            ],
            ssl: [
                'is_valid' => true,
                'expires_at' => now()->addDays(30)->toIso8601String(),
                'days_until_expiry' => 30,
                'issuer' => 'Example CA',
                'protocol' => 'TLSv1.3',
                'cipher' => 'TLS_AES_256_GCM_SHA384',
                'chain' => [],
                'error_message' => null,
                'payload' => ['domain' => 'redirect.example.au', 'days_until_expiry' => 30],
            ],
        );

        Http::fake([
            'http://redirect.example.au/' => Http::response(
                'ok',
                200,
                ['X-Guzzle-Redirect-History' => [], 'X-Guzzle-Redirect-Status-History' => []]
            ),
            'https://www.redirect.example.au/' => Http::response(
                'ok',
                200,
                ['X-Guzzle-Redirect-History' => [], 'X-Guzzle-Redirect-Status-History' => []]
            ),
        ]);

        $brain = Mockery::mock(BrainEventClient::class);
        $this->instance(BrainEventClient::class, $brain);
        /** @var Mockery\Expectation $criticalLiveExpectation */
        $criticalLiveExpectation = $brain->shouldReceive('sendAsync');
        $criticalLiveExpectation->once()->withArgs(function (string $eventType, array $payload): bool {
            $this->assertSame('domain_monitor.finding.opened', $eventType);
            $this->assertSame('critical.redirect_policy', $payload['finding_type']);
            $this->assertSame('incident', $payload['issue_type']);

            return true;
        });

        $this->assertSame(0, Artisan::call('monitoring:run-lane', [
            'lane' => 'critical_live',
            '--property' => $property->slug,
        ]));

        $finding = MonitoringFinding::query()
            ->where('web_property_id', $property->id)
            ->where('finding_type', 'critical.redirect_policy')
            ->firstOrFail();

        $this->assertSame(MonitoringFinding::STATUS_OPEN, $finding->status);
        $this->assertSame('http_upgrade_failed', data_get($finding->evidence, 'verdict'));
    }

    public function test_critical_live_lane_reports_uptime_http_and_ssl_failures(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $property = $this->makeProperty('critical-live.example.au', 'Critical Live');
        $property->forceFill([
            'canonical_origin_scheme' => 'https',
            'canonical_origin_host' => 'critical-live.example.au',
            'canonical_origin_policy' => 'known',
        ])->save();

        $this->mockCriticalLiveHealthChecks(
            uptime: [
                'is_valid' => false,
                'status_code' => null,
                'duration_ms' => 20,
                'error_message' => 'Connection timed out',
                'payload' => ['url' => 'https://critical-live.example.au', 'error_type' => 'exception', 'duration_ms' => 20],
            ],
            http: [
                'status_code' => 503,
                'duration_ms' => 25,
                'is_up' => false,
                'error_message' => 'HTTP 503',
                'payload' => ['url' => 'https://critical-live.example.au', 'headers' => [], 'redirected' => false],
            ],
            ssl: [
                'is_valid' => false,
                'expires_at' => null,
                'days_until_expiry' => null,
                'issuer' => null,
                'protocol' => null,
                'cipher' => null,
                'chain' => [],
                'error_message' => 'ssl connect failed',
                'payload' => ['domain' => 'critical-live.example.au', 'error_type' => 'connection'],
            ],
        );

        Http::fake([
            'http://critical-live.example.au/' => Http::response(
                '',
                200,
                ['X-Guzzle-Redirect-History' => ['https://critical-live.example.au/'], 'X-Guzzle-Redirect-Status-History' => ['301']]
            ),
            'https://www.critical-live.example.au/' => Http::response(
                '',
                200,
                ['X-Guzzle-Redirect-History' => ['https://critical-live.example.au/'], 'X-Guzzle-Redirect-Status-History' => ['301']]
            ),
        ]);

        $brain = Mockery::mock(BrainEventClient::class);
        $this->instance(BrainEventClient::class, $brain);
        /** @var Mockery\Expectation $criticalLiveExpectation */
        $criticalLiveExpectation = $brain->shouldReceive('sendAsync');
        $criticalLiveExpectation->times(3)->withArgs(
            fn (string $eventType, array $payload): bool => $eventType === 'domain_monitor.finding.opened'
                && in_array($payload['finding_type'], ['critical.uptime', 'critical.http_response', 'critical.ssl'], true)
                && $payload['issue_type'] === 'incident'
        );

        $this->assertSame(0, Artisan::call('monitoring:run-lane', [
            'lane' => 'critical_live',
            '--property' => $property->slug,
        ]));

        $this->assertDatabaseHas('monitoring_findings', [
            'web_property_id' => $property->id,
            'finding_type' => 'critical.uptime',
            'status' => MonitoringFinding::STATUS_OPEN,
        ]);
        $this->assertDatabaseHas('monitoring_findings', [
            'web_property_id' => $property->id,
            'finding_type' => 'critical.http_response',
            'status' => MonitoringFinding::STATUS_OPEN,
        ]);
        $this->assertDatabaseHas('monitoring_findings', [
            'web_property_id' => $property->id,
            'finding_type' => 'critical.ssl',
            'status' => MonitoringFinding::STATUS_OPEN,
        ]);

        $issues = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->json('issues');

        $this->assertIsArray($issues);
        $criticalIssue = collect($issues)->firstWhere('issue_class', 'critical.uptime');

        $this->assertIsArray($criticalIssue);
        $this->assertSame('must_fix', $criticalIssue['severity']);
    }

    public function test_deep_audit_lane_opens_broken_links_findings_and_dedupes_issue_surface(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $property = $this->makeProperty('deep-audit.example.au', 'Deep Audit');

        $brokenLinkHealthCheck = Mockery::mock(BrokenLinkHealthCheck::class);
        /** @var Mockery\Expectation $brokenLinksExpectation */
        $brokenLinksExpectation = $brokenLinkHealthCheck->shouldReceive('check');
        $brokenLinksExpectation->once()->andReturn([
            'is_valid' => false,
            'verified' => true,
            'broken_links_count' => 2,
            'pages_scanned' => 8,
            'broken_links' => [
                [
                    'url' => 'https://deep-audit.example.au/missing-page/',
                    'status' => 404,
                    'found_on' => 'https://deep-audit.example.au/services/',
                ],
                [
                    'url' => 'https://deep-audit.example.au/old-booking/',
                    'status' => 410,
                    'found_on' => 'https://deep-audit.example.au/contact/',
                ],
            ],
            'error_message' => null,
            'payload' => [
                'broken_links_count' => 2,
                'pages_scanned' => 8,
                'broken_links' => [
                    [
                        'url' => 'https://deep-audit.example.au/missing-page/',
                        'status' => 404,
                        'found_on' => 'https://deep-audit.example.au/services/',
                    ],
                    [
                        'url' => 'https://deep-audit.example.au/old-booking/',
                        'status' => 410,
                        'found_on' => 'https://deep-audit.example.au/contact/',
                    ],
                ],
                'duration_ms' => 18,
            ],
        ]);
        $this->instance(BrokenLinkHealthCheck::class, $brokenLinkHealthCheck);

        $externalLinkInventoryHealthCheck = Mockery::mock(ExternalLinkInventoryHealthCheck::class);
        /** @var Mockery\Expectation $externalLinksClearExpectation */
        $externalLinksClearExpectation = $externalLinkInventoryHealthCheck->shouldReceive('check');
        $externalLinksClearExpectation->once()->andReturn([
            'is_valid' => true,
            'verified' => true,
            'external_links_count' => 0,
            'pages_scanned' => 4,
            'external_links' => [],
            'error_message' => null,
            'payload' => [
                'pages_scanned' => 4,
                'external_links_count' => 0,
                'unique_hosts_count' => 0,
                'page_failures_count' => 0,
                'external_links' => [],
                'duration_ms' => 12,
            ],
        ]);
        $this->instance(ExternalLinkInventoryHealthCheck::class, $externalLinkInventoryHealthCheck);
        app()->forgetInstance(\App\Services\DomainHealthCheckRunner::class);

        $brain = Mockery::mock(BrainEventClient::class);
        $this->instance(BrainEventClient::class, $brain);
        /** @var Mockery\Expectation $deepAuditExpectation */
        $deepAuditExpectation = $brain->shouldReceive('sendAsync');
        $deepAuditExpectation->once()->withArgs(function (string $eventType, array $payload): bool {
            $this->assertSame('domain_monitor.finding.opened', $eventType);
            $this->assertSame('seo.broken_links', $payload['finding_type']);
            $this->assertSame('cleanup', $payload['issue_type']);

            return true;
        });

        $this->assertSame(0, Artisan::call('monitoring:run-lane', [
            'lane' => 'deep_audit',
            '--property' => $property->slug,
        ]));

        $primaryDomain = $property->primaryDomainModel();
        $this->assertInstanceOf(Domain::class, $primaryDomain);

        $this->assertDatabaseHas('domain_checks', [
            'domain_id' => $primaryDomain->id,
            'check_type' => 'broken_links',
            'status' => 'fail',
        ]);
        $this->assertDatabaseHas('monitoring_findings', [
            'web_property_id' => $property->id,
            'finding_type' => 'seo.broken_links',
            'status' => MonitoringFinding::STATUS_OPEN,
        ]);

        $issues = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->json('issues');

        $this->assertIsArray($issues);
        $this->assertSame(1, collect($issues)->where('issue_class', 'seo.broken_links')->count());

        /** @var array<string, mixed>|null $brokenLinksIssue */
        $brokenLinksIssue = collect($issues)->firstWhere('issue_class', 'seo.broken_links');
        $this->assertIsArray($brokenLinksIssue);
        $this->assertSame('domain_monitor.monitoring_lane', $brokenLinksIssue['detector']);
        $this->assertSame(2, data_get($brokenLinksIssue, 'evidence.broken_links_count'));
    }

    public function test_deep_audit_lane_opens_external_link_inventory_findings_for_off_host_links(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $property = $this->makeProperty('external-links.example.au', 'External Links');

        $brokenLinkHealthCheck = Mockery::mock(BrokenLinkHealthCheck::class);
        /** @var Mockery\Expectation $brokenLinksClearExpectation */
        $brokenLinksClearExpectation = $brokenLinkHealthCheck->shouldReceive('check');
        $brokenLinksClearExpectation->once()->andReturn([
            'is_valid' => true,
            'verified' => true,
            'broken_links_count' => 0,
            'pages_scanned' => 3,
            'broken_links' => [],
            'error_message' => null,
            'payload' => [
                'broken_links_count' => 0,
                'pages_scanned' => 3,
                'broken_links' => [],
                'duration_ms' => 10,
            ],
        ]);
        $this->instance(BrokenLinkHealthCheck::class, $brokenLinkHealthCheck);

        $externalLinkInventoryHealthCheck = Mockery::mock(ExternalLinkInventoryHealthCheck::class);
        /** @var Mockery\Expectation $externalLinksDetectedExpectation */
        $externalLinksDetectedExpectation = $externalLinkInventoryHealthCheck->shouldReceive('check');
        $externalLinksDetectedExpectation->once()->andReturn([
            'is_valid' => true,
            'verified' => true,
            'external_links_count' => 3,
            'pages_scanned' => 5,
            'external_links' => [
                [
                    'url' => 'https://partner.example.org/quote',
                    'host' => 'partner.example.org',
                    'relationship' => 'external',
                    'found_on' => 'https://external-links.example.au/services/',
                    'found_on_pages' => ['https://external-links.example.au/services/'],
                ],
                [
                    'url' => 'https://facebook.com/example',
                    'host' => 'facebook.com',
                    'relationship' => 'external',
                    'found_on' => 'https://external-links.example.au/contact/',
                    'found_on_pages' => ['https://external-links.example.au/contact/'],
                ],
                [
                    'url' => 'https://blog.external-links.example.au/post',
                    'host' => 'blog.external-links.example.au',
                    'relationship' => 'subdomain',
                    'found_on' => 'https://external-links.example.au/',
                    'found_on_pages' => ['https://external-links.example.au/'],
                ],
            ],
            'error_message' => null,
            'payload' => [
                'pages_scanned' => 5,
                'external_links_count' => 3,
                'unique_hosts_count' => 3,
                'page_failures_count' => 0,
                'external_links' => [
                    [
                        'url' => 'https://partner.example.org/quote',
                        'host' => 'partner.example.org',
                        'relationship' => 'external',
                        'found_on' => 'https://external-links.example.au/services/',
                        'found_on_pages' => ['https://external-links.example.au/services/'],
                    ],
                    [
                        'url' => 'https://facebook.com/example',
                        'host' => 'facebook.com',
                        'relationship' => 'external',
                        'found_on' => 'https://external-links.example.au/contact/',
                        'found_on_pages' => ['https://external-links.example.au/contact/'],
                    ],
                    [
                        'url' => 'https://blog.external-links.example.au/post',
                        'host' => 'blog.external-links.example.au',
                        'relationship' => 'subdomain',
                        'found_on' => 'https://external-links.example.au/',
                        'found_on_pages' => ['https://external-links.example.au/'],
                    ],
                ],
                'duration_ms' => 14,
            ],
        ]);
        $this->instance(ExternalLinkInventoryHealthCheck::class, $externalLinkInventoryHealthCheck);
        app()->forgetInstance(\App\Services\DomainHealthCheckRunner::class);

        $brain = Mockery::mock(BrainEventClient::class);
        $this->instance(BrainEventClient::class, $brain);
        /** @var Mockery\Expectation $externalLinksOpenedExpectation */
        $externalLinksOpenedExpectation = $brain->shouldReceive('sendAsync');
        $externalLinksOpenedExpectation->once()->withArgs(function (string $eventType, array $payload): bool {
            $this->assertSame('domain_monitor.finding.opened', $eventType);
            $this->assertSame('cleanup.external_links_inventory', $payload['finding_type']);
            $this->assertSame('cleanup', $payload['issue_type']);

            return true;
        });

        $this->assertSame(0, Artisan::call('monitoring:run-lane', [
            'lane' => 'deep_audit',
            '--property' => $property->slug,
        ]));

        $this->assertDatabaseHas('monitoring_findings', [
            'web_property_id' => $property->id,
            'finding_type' => 'cleanup.external_links_inventory',
            'status' => MonitoringFinding::STATUS_OPEN,
        ]);

        $issues = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/issues')
            ->assertOk()
            ->json('issues');

        $this->assertIsArray($issues);

        /** @var array<string, mixed>|null $externalLinksIssue */
        $externalLinksIssue = collect($issues)->firstWhere('issue_class', 'cleanup.external_links_inventory');
        $this->assertIsArray($externalLinksIssue);
        $this->assertSame('should_fix', $externalLinksIssue['severity']);
        $this->assertSame(2, data_get($externalLinksIssue, 'evidence.reviewable_external_links_count'));
        $this->assertSame(['facebook.com', 'partner.example.org'], data_get($externalLinksIssue, 'evidence.unique_hosts'));
    }

    public function test_deep_audit_lane_does_not_open_external_link_inventory_findings_for_subdomains_only(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');

        $property = $this->makeProperty('subdomain-inventory.example.au', 'Subdomain Inventory');

        $brokenLinkHealthCheck = Mockery::mock(BrokenLinkHealthCheck::class);
        /** @var Mockery\Expectation $brokenLinksStillClearExpectation */
        $brokenLinksStillClearExpectation = $brokenLinkHealthCheck->shouldReceive('check');
        $brokenLinksStillClearExpectation->once()->andReturn([
            'is_valid' => true,
            'verified' => true,
            'broken_links_count' => 0,
            'pages_scanned' => 2,
            'broken_links' => [],
            'error_message' => null,
            'payload' => [
                'broken_links_count' => 0,
                'pages_scanned' => 2,
                'broken_links' => [],
                'duration_ms' => 10,
            ],
        ]);
        $this->instance(BrokenLinkHealthCheck::class, $brokenLinkHealthCheck);

        $externalLinkInventoryHealthCheck = Mockery::mock(ExternalLinkInventoryHealthCheck::class);
        /** @var Mockery\Expectation $subdomainInventoryExpectation */
        $subdomainInventoryExpectation = $externalLinkInventoryHealthCheck->shouldReceive('check');
        $subdomainInventoryExpectation->once()->andReturn([
            'is_valid' => true,
            'verified' => true,
            'external_links_count' => 2,
            'pages_scanned' => 4,
            'external_links' => [
                [
                    'url' => 'https://blog.subdomain-inventory.example.au/post',
                    'host' => 'blog.subdomain-inventory.example.au',
                    'relationship' => 'subdomain',
                    'found_on' => 'https://subdomain-inventory.example.au/',
                    'found_on_pages' => ['https://subdomain-inventory.example.au/'],
                ],
                [
                    'url' => 'https://example.au/about',
                    'host' => 'example.au',
                    'relationship' => 'parent_domain',
                    'found_on' => 'https://subdomain-inventory.example.au/contact/',
                    'found_on_pages' => ['https://subdomain-inventory.example.au/contact/'],
                ],
            ],
            'error_message' => null,
            'payload' => [
                'pages_scanned' => 4,
                'external_links_count' => 2,
                'unique_hosts_count' => 2,
                'page_failures_count' => 0,
                'external_links' => [
                    [
                        'url' => 'https://blog.subdomain-inventory.example.au/post',
                        'host' => 'blog.subdomain-inventory.example.au',
                        'relationship' => 'subdomain',
                        'found_on' => 'https://subdomain-inventory.example.au/',
                        'found_on_pages' => ['https://subdomain-inventory.example.au/'],
                    ],
                    [
                        'url' => 'https://example.au/about',
                        'host' => 'example.au',
                        'relationship' => 'parent_domain',
                        'found_on' => 'https://subdomain-inventory.example.au/contact/',
                        'found_on_pages' => ['https://subdomain-inventory.example.au/contact/'],
                    ],
                ],
                'duration_ms' => 12,
            ],
        ]);
        $this->instance(ExternalLinkInventoryHealthCheck::class, $externalLinkInventoryHealthCheck);
        app()->forgetInstance(\App\Services\DomainHealthCheckRunner::class);

        $brain = Mockery::mock(BrainEventClient::class);
        $this->instance(BrainEventClient::class, $brain);
        $brain->shouldIgnoreMissing();

        $this->assertSame(0, Artisan::call('monitoring:run-lane', [
            'lane' => 'deep_audit',
            '--property' => $property->slug,
        ]));

        $this->assertSame(0, MonitoringFinding::query()->count());
        $this->assertNull(MonitoringFinding::query()
            ->where('web_property_id', $property->id)
            ->where('finding_type', 'cleanup.external_links_inventory')
            ->first());
    }

    public function test_deep_audit_lane_does_not_open_external_link_inventory_findings_for_gov_au_hosts(): void
    {
        config()->set('services.brain.base_url', 'https://brain.example.test');
        config()->set('services.brain.api_key', 'test-key');

        $property = $this->makeProperty('government-links.example.au', 'Government Links');

        $brokenLinkHealthCheck = Mockery::mock(BrokenLinkHealthCheck::class);
        /** @var Mockery\Expectation $brokenLinksClearExpectation */
        $brokenLinksClearExpectation = $brokenLinkHealthCheck->shouldReceive('check');
        $brokenLinksClearExpectation->once()->andReturn([
            'is_valid' => true,
            'verified' => true,
            'broken_links_count' => 0,
            'pages_scanned' => 2,
            'broken_links' => [],
            'error_message' => null,
            'payload' => [
                'broken_links_count' => 0,
                'pages_scanned' => 2,
                'broken_links' => [],
                'duration_ms' => 10,
            ],
        ]);
        $this->instance(BrokenLinkHealthCheck::class, $brokenLinkHealthCheck);

        $externalLinkInventoryHealthCheck = Mockery::mock(ExternalLinkInventoryHealthCheck::class);
        /** @var Mockery\Expectation $governmentInventoryExpectation */
        $governmentInventoryExpectation = $externalLinkInventoryHealthCheck->shouldReceive('check');
        $governmentInventoryExpectation->once()->andReturn([
            'is_valid' => true,
            'verified' => true,
            'external_links_count' => 2,
            'pages_scanned' => 4,
            'external_links' => [
                [
                    'url' => 'https://www.oaic.gov.au/privacy',
                    'host' => 'www.oaic.gov.au',
                    'relationship' => 'external',
                    'found_on' => 'https://government-links.example.au/privacy/',
                    'found_on_pages' => ['https://government-links.example.au/privacy/'],
                ],
                [
                    'url' => 'https://www.servicesaustralia.gov.au/',
                    'host' => 'www.servicesaustralia.gov.au',
                    'relationship' => 'external',
                    'found_on' => 'https://government-links.example.au/support/',
                    'found_on_pages' => ['https://government-links.example.au/support/'],
                ],
            ],
            'error_message' => null,
            'payload' => [
                'pages_scanned' => 4,
                'external_links_count' => 2,
                'unique_hosts_count' => 2,
                'page_failures_count' => 0,
                'external_links' => [
                    [
                        'url' => 'https://www.oaic.gov.au/privacy',
                        'host' => 'www.oaic.gov.au',
                        'relationship' => 'external',
                        'found_on' => 'https://government-links.example.au/privacy/',
                        'found_on_pages' => ['https://government-links.example.au/privacy/'],
                    ],
                    [
                        'url' => 'https://www.servicesaustralia.gov.au/',
                        'host' => 'www.servicesaustralia.gov.au',
                        'relationship' => 'external',
                        'found_on' => 'https://government-links.example.au/support/',
                        'found_on_pages' => ['https://government-links.example.au/support/'],
                    ],
                ],
                'duration_ms' => 12,
            ],
        ]);
        $this->instance(ExternalLinkInventoryHealthCheck::class, $externalLinkInventoryHealthCheck);
        app()->forgetInstance(\App\Services\DomainHealthCheckRunner::class);

        $brain = Mockery::mock(BrainEventClient::class);
        $this->instance(BrainEventClient::class, $brain);
        $brain->shouldIgnoreMissing();

        $this->assertSame(0, Artisan::call('monitoring:run-lane', [
            'lane' => 'deep_audit',
            '--property' => $property->slug,
        ]));

        $this->assertSame(0, MonitoringFinding::query()->count());
        $this->assertNull(MonitoringFinding::query()
            ->where('web_property_id', $property->id)
            ->where('finding_type', 'cleanup.external_links_inventory')
            ->first());
    }

    private function makeProperty(string $domainName, string $name): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'is_active' => true,
            'platform' => 'Astro',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => str($domainName)->replace('.', '-')->toString(),
            'name' => $name,
            'status' => 'active',
            'property_type' => 'marketing_site',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://'.$domainName.'/',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        return $property;
    }

    private function homepageHtml(
        ?string $canonical = null,
        ?string $measurementId = null,
        ?string $metaRobots = null,
        bool $includeStructuredData = false,
        string $body = 'Body'
    ): string {
        $head = '<title>Test Page</title>';

        if ($canonical !== null) {
            $head .= sprintf('<link rel="canonical" href="%s" />', $canonical);
        }

        if ($metaRobots !== null) {
            $head .= sprintf('<meta name="robots" content="%s" />', $metaRobots);
        }

        if ($measurementId !== null) {
            $head .= sprintf(
                '<script async src="https://www.googletagmanager.com/gtag/js?id=%1$s"></script><script>gtag("config","%1$s");</script>',
                $measurementId
            );
        }

        if ($includeStructuredData) {
            $head .= '<script type="application/ld+json">{"@context":"https://schema.org","@type":"Organization","name":"Example"}</script>';
        }

        return sprintf('<html><head>%s</head><body>%s</body></html>', $head, $body);
    }

    /**
     * @param  array<string, mixed>  $uptime
     * @param  array<string, mixed>  $http
     * @param  array<string, mixed>  $ssl
     */
    private function mockCriticalLiveHealthChecks(array $uptime, array $http, array $ssl): void
    {
        $uptimeHealthCheck = Mockery::mock(UptimeHealthCheck::class);
        /** @var Mockery\Expectation $uptimeExpectation */
        $uptimeExpectation = $uptimeHealthCheck->shouldReceive('check');
        $uptimeExpectation->andReturn($uptime);
        $this->instance(UptimeHealthCheck::class, $uptimeHealthCheck);

        $httpHealthCheck = Mockery::mock(HttpHealthCheck::class);
        /** @var Mockery\Expectation $httpExpectation */
        $httpExpectation = $httpHealthCheck->shouldReceive('check');
        $httpExpectation->andReturn($http);
        $this->instance(HttpHealthCheck::class, $httpHealthCheck);

        $sslHealthCheck = Mockery::mock(SslHealthCheck::class);
        /** @var Mockery\Expectation $sslExpectation */
        $sslExpectation = $sslHealthCheck->shouldReceive('check');
        $sslExpectation->andReturn($ssl);
        $this->instance(SslHealthCheck::class, $sslHealthCheck);

        app()->forgetInstance(PropertySiteSignalScanner::class);
    }
}
