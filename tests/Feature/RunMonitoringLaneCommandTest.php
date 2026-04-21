<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\MonitoringFinding;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Models\WebPropertyConversionSurface;
use App\Models\WebPropertyDomain;
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
            'https://tracked.example.au/' => Http::response('<html><head><title>No GA4</title></head><body>Missing</body></html>', 200),
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
            'https://brand.example.au/' => Http::response(
                "<script async src=\"https://www.googletagmanager.com/gtag/js?id=G-EXPECTED999\"></script><script>gtag('config','G-EXPECTED999');</script>",
                200
            ),
            'https://quotes.brand.example.au/' => Http::response(
                "<script async src=\"https://www.googletagmanager.com/gtag/js?id=G-WRONG000\"></script><script>gtag('config','G-WRONG000');</script>",
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

    public function test_marketing_integrity_lane_skips_properties_without_an_active_primary_ga4_source(): void
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
                "<script async src=\"https://www.googletagmanager.com/gtag/js?id=G-ACTIVE999\"></script><script>gtag('config','G-ACTIVE999');</script>",
                200
            ),
        ]);

        $brain = Mockery::mock(BrainEventClient::class);
        $this->instance(BrainEventClient::class, $brain);
        $brain->shouldNotReceive('sendAsync');

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
        ]);

        $this->assertDatabaseHas('property_analytics_sources', [
            'id' => $primarySource->id,
            'status' => 'inactive',
            'is_primary' => true,
        ]);
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
}
