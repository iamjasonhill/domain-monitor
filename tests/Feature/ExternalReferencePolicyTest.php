<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\WebProperty;
use App\Models\WebPropertyConversionSurface;
use App\Models\WebPropertyDomain;
use App\Services\DomainHealthCheckRunner;
use App\Services\ExternalLinkInventoryHealthCheck;
use App\Services\ExternalReferencePolicy;
use App\Services\PropertySiteSignalScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ExternalReferencePolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_classifies_owned_estate_links_from_domain_monitor_inventory(): void
    {
        Domain::factory()->create(['domain' => 'owned-reference.example.au']);

        $policy = app(ExternalReferencePolicy::class)->classify('owned-reference.example.au', 'movingagain.com.au');

        $this->assertSame('owned_estate', $policy['classification']);
        $this->assertSame('approved', $policy['action']);
        $this->assertTrue($policy['approved']);
    }

    public function test_it_approves_moving_insurance_as_fleet_external_reference(): void
    {
        $policy = app(ExternalReferencePolicy::class)->classify('movinginsurance.com.au', 'backloadingremovals.com.au');

        $this->assertSame('approved_partner', $policy['classification']);
        $this->assertSame('approved', $policy['action']);
        $this->assertSame('approved_external_reference', $policy['category']);
        $this->assertTrue($policy['approved']);
        $this->assertStringContainsString('Moving Insurance', $policy['reason']);
    }

    public function test_it_classifies_operational_surfaces_for_the_source_property(): void
    {
        $property = $this->property('movingagain.com.au', [
            'target_moveroo_subdomain_url' => 'https://quote.movingagain.com.au/start',
        ]);

        WebPropertyConversionSurface::create([
            'web_property_id' => $property->id,
            'hostname' => 'booking.movingagain.com.au',
            'surface_type' => 'quote_subdomain',
            'analytics_binding_mode' => 'inherits_property',
            'event_contract_binding_mode' => 'inherits_property',
            'rollout_status' => 'defined',
        ]);

        $targetPolicy = app(ExternalReferencePolicy::class)->classify('quote.movingagain.com.au', 'movingagain.com.au', $property);
        $surfacePolicy = app(ExternalReferencePolicy::class)->classify('booking.movingagain.com.au', 'movingagain.com.au', $property);

        $this->assertSame('operational_surface', $targetPolicy['classification']);
        $this->assertSame('operational_surface', $surfacePolicy['classification']);
        $this->assertTrue($surfacePolicy['approved']);
    }

    public function test_it_classifies_authority_partner_unknown_and_disallowed_hosts(): void
    {
        config()->set('domain_monitor.external_reference_policy.approved_partner_hosts', [
            [
                'host' => 'partner.example.org',
                'category' => 'referral_partner',
                'reason' => 'Approved referral partner.',
            ],
        ]);
        config()->set('domain_monitor.external_reference_policy.disallowed_hosts', [
            'spam.example.test',
        ]);

        $policy = app(ExternalReferencePolicy::class);

        $authority = $policy->classify('www.abs.gov.au', 'movingagain.com.au');
        $partner = $policy->classify('partner.example.org', 'movingagain.com.au');
        $unknown = $policy->classify('unknown.example.net', 'movingagain.com.au');
        $disallowed = $policy->classify('spam.example.test', 'movingagain.com.au');

        $this->assertSame('authority_reference', $authority['classification']);
        $this->assertSame('government', $authority['category']);
        $this->assertSame('approved_partner', $partner['classification']);
        $this->assertSame('referral_partner', $partner['category']);
        $this->assertSame('review_required', $unknown['classification']);
        $this->assertSame('review_required', $unknown['action']);
        $this->assertSame('disallowed', $disallowed['classification']);
        $this->assertSame('disallowed', $disallowed['action']);
    }

    public function test_external_link_inventory_output_includes_policy_classification_and_counts(): void
    {
        config()->set('domain_monitor.external_reference_policy.disallowed_hosts', ['spam.example.test']);

        Http::fake(function (Request $request) {
            return match ($request->url()) {
                'https://movingagain.com.au/' => Http::response(
                    <<<'HTML'
                    <html>
                        <body>
                            <a href="https://quote.movingagain.com.au/start">Quote</a>
                            <a href="https://www.abs.gov.au/statistics">ABS</a>
                            <a href="https://movinginsurance.com.au/">Insurance</a>
                            <a href="https://unknown.example.net/info">Unknown</a>
                            <a href="https://spam.example.test/bad">Spam</a>
                        </body>
                    </html>
                    HTML,
                    200,
                    ['Content-Type' => 'text/html']
                ),
                default => Http::response('not found', 404),
            };
        });

        $result = app(ExternalLinkInventoryHealthCheck::class)->check('movingagain.com.au');
        $links = collect($result['external_links']);

        $this->assertSame('operational_surface', $links->firstWhere('host', 'quote.movingagain.com.au')['policy_classification']);
        $this->assertSame('authority_reference', $links->firstWhere('host', 'www.abs.gov.au')['policy_classification']);
        $this->assertSame('approved_partner', $links->firstWhere('host', 'movinginsurance.com.au')['policy_classification']);
        $this->assertSame('approved_external_reference', $links->firstWhere('host', 'movinginsurance.com.au')['policy_category']);
        $this->assertSame('review_required', $links->firstWhere('host', 'unknown.example.net')['policy_classification']);
        $this->assertSame('disallowed', $links->firstWhere('host', 'spam.example.test')['policy_classification']);
        $this->assertSame(3, data_get($result, 'payload.policy_counts.approved'));
        $this->assertSame(1, data_get($result, 'payload.policy_counts.review_required'));
        $this->assertSame(1, data_get($result, 'payload.policy_counts.disallowed'));
    }

    public function test_deep_audit_only_fails_for_review_required_or_disallowed_external_links(): void
    {
        config()->set('domain_monitor.external_reference_policy.disallowed_hosts', ['spam.example.test']);

        $property = $this->property('movingagain.com.au');
        $domain = $property->primaryDomainModel();
        $this->assertInstanceOf(Domain::class, $domain);

        DomainCheck::withoutEvents(function () use ($domain): void {
            DomainCheck::factory()->create([
                'id' => (string) Str::uuid(),
                'domain_id' => $domain->id,
                'check_type' => 'external_links',
                'status' => 'ok',
                'finished_at' => now(),
                'payload' => [
                    'pages_scanned' => 1,
                    'page_failures_count' => 0,
                    'external_links' => [
                        $this->payloadLink('https://quote.movingagain.com.au/start', 'quote.movingagain.com.au'),
                        $this->payloadLink('https://www.abs.gov.au/statistics', 'www.abs.gov.au'),
                        $this->payloadLink('https://movinginsurance.com.au/', 'movinginsurance.com.au'),
                        $this->payloadLink('https://unknown.example.net/info', 'unknown.example.net'),
                        $this->payloadLink('https://spam.example.test/bad', 'spam.example.test'),
                    ],
                ],
                'retry_count' => 0,
            ]);
        });

        $runner = Mockery::mock(DomainHealthCheckRunner::class);
        $runner->shouldReceive('run')->once()->andReturn([
            'status' => 'refreshed',
            'checked_at' => now()->toIso8601String(),
            'reason' => null,
        ]);

        $result = app(PropertySiteSignalScanner::class)->auditExternalLinks($property->fresh(['primaryDomain', 'conversionSurfaces']), $runner);

        $this->assertSame('fail', $result['status']);
        $this->assertSame('external_links_detected', $result['verdict']);
        $this->assertSame(2, data_get($result, 'evidence.reviewable_external_links_count'));
        $this->assertSame(['spam.example.test', 'unknown.example.net'], data_get($result, 'evidence.unique_hosts'));
        $this->assertSame(3, data_get($result, 'evidence.policy_counts.approved'));
        $this->assertSame(1, data_get($result, 'evidence.policy_counts.review_required'));
        $this->assertSame(1, data_get($result, 'evidence.policy_counts.disallowed'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function property(string $domainName, array $attributes = []): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => str($domainName)->replace('.', '-')->toString(),
            'name' => $domainName,
            'primary_domain_id' => $domain->id,
            'status' => 'active',
            'property_type' => 'marketing_site',
            ...$attributes,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        return $property;
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadLink(string $url, string $host): array
    {
        return [
            'url' => $url,
            'host' => $host,
            'relationship' => 'external',
            'found_on' => 'https://movingagain.com.au/',
            'found_on_pages' => ['https://movingagain.com.au/'],
        ];
    }
}
