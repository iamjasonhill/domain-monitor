<?php

namespace Tests\Feature;

use App\Models\AnalyticsEventContract;
use App\Models\AnalyticsInstallAudit;
use App\Models\Domain;
use App\Models\DomainAlert;
use App\Models\DomainCheck;
use App\Models\DomainSeoBaseline;
use App\Models\DomainTag;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use App\Models\WebPropertyEventContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WebPropertyApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_properties_summary_returns_contract_v1_payload(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $primaryDomain = Domain::factory()->create([
            'domain' => 'moveroo.com.au',
            'platform' => 'Astro',
            'hosting_provider' => 'Vercel',
        ]);

        $redirectDomain = Domain::factory()->create([
            'domain' => 'www.moveroo.com.au',
            'platform' => 'Astro',
            'hosting_provider' => 'Vercel',
        ]);

        $fleetTag = DomainTag::firstOrCreate(
            ['name' => 'fleet.live'],
            [
                'priority' => 95,
                'color' => '#2563EB',
                'description' => 'Fleet-managed live domains.',
            ]
        );

        $property = WebProperty::factory()->create([
            'slug' => 'moveroo-website',
            'name' => 'Moveroo Website',
            'site_identity_site_name' => 'Moveroo',
            'site_identity_legal_name' => 'Moveroo Australia',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'platform' => 'Astro',
            'primary_domain_id' => $primaryDomain->id,
            'production_url' => 'https://moveroo.com.au',
            'canonical_origin_scheme' => 'https',
            'canonical_origin_host' => 'moveroo.com.au',
            'canonical_origin_policy' => 'known',
            'canonical_origin_enforcement_eligible' => true,
            'canonical_origin_excluded_subdomains' => ['cartransport.moveroo.com.au'],
            'canonical_origin_sitemap_policy_known' => true,
            'current_household_quote_url' => 'https://removalists.moveroo.com.au/quote/household',
            'current_household_booking_url' => 'https://removalists.moveroo.com.au/booking/create',
            'current_vehicle_quote_url' => 'https://cars.moveroo.com.au/quote/v2',
            'target_household_quote_url' => 'https://quote.moveroo.com.au/household',
            'target_vehicle_quote_url' => 'https://quote.moveroo.com.au/vehicle',
            'target_moveroo_subdomain_url' => 'https://wemove.moveroo.com.au',
            'target_contact_us_page_url' => 'https://moveroo.com.au/contact-us',
            'target_legacy_bookings_replacement_url' => 'https://removalist.net/booking/create',
            'target_legacy_payments_replacement_url' => 'https://wemove.moveroo.com.au/contact',
            'conversion_links_scanned_at' => now(),
            'legacy_moveroo_endpoint_scan' => [
                'legacy_booking_endpoint' => [
                    'classification' => 'legacy_booking_endpoint',
                    'found_on' => 'https://moveroo.com.au',
                    'url' => 'https://wemove.moveroo.com.au/bookings',
                    'resolved_url' => 'https://removalist.net/booking/create',
                    'resolved_status' => 200,
                    'resolved_host_changed' => true,
                ],
                'legacy_payment_endpoint' => [
                    'classification' => 'legacy_payment_endpoint',
                    'found_on' => 'https://moveroo.com.au',
                    'url' => 'https://wemove.moveroo.com.au/payments',
                    'resolved_url' => 'https://wemove.moveroo.com.au/contact',
                    'resolved_status' => 200,
                    'resolved_host_changed' => false,
                ],
            ],
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $redirectDomain->id,
            'usage_type' => 'redirect',
            'is_canonical' => false,
        ]);

        $ownedSubdomain = Domain::factory()->create([
            'domain' => 'quoting.moveroo.com.au',
            'platform' => 'Astro',
            'hosting_provider' => 'Vercel',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $ownedSubdomain->id,
            'usage_type' => 'subdomain',
            'is_canonical' => false,
        ]);

        $primaryDomain->tags()->syncWithoutDetaching([$fleetTag->id]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'moveroo/moveroo-website-astro',
            'repo_provider' => 'github',
            'repo_url' => 'https://github.com/moveroo/moveroo-website-astro',
            'local_path' => '/Users/jasonhill/Projects/websites/moveroo-website-astro',
            'framework' => 'Astro',
            'is_primary' => true,
            'is_controller' => true,
            'deployment_provider' => 'vercel',
            'deployment_project_name' => 'moveroo-website',
            'deployment_project_id' => 'prj_moveroo123',
        ]);

        PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '6',
            'external_name' => 'Moveroo website',
            'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
            'is_primary' => true,
        ]);

        $eventContract = AnalyticsEventContract::create([
            'key' => 'shared-ga4-baseline-v1',
            'name' => 'Shared GA4 Baseline',
            'version' => 'v1',
            'contract_type' => 'ga4_web',
            'status' => 'active',
            'scope' => 'portfolio_default',
            'source_repo' => 'MM-Google',
            'source_path' => 'docs/event-taxonomy.md',
            'contract' => [
                'recommended_events' => ['generate_lead', 'phone_click', 'form_submit'],
                'key_events' => ['generate_lead', 'phone_click'],
                'standard_parameters' => ['site_key', 'lead_type'],
            ],
        ]);

        WebPropertyEventContract::create([
            'web_property_id' => $property->id,
            'analytics_event_contract_id' => $eventContract->id,
            'is_primary' => true,
            'rollout_status' => 'defined',
            'notes' => 'Backfilled from MM-Google.',
        ]);

        $source = PropertyAnalyticsSource::query()->where('web_property_id', $property->id)->firstOrFail();

        DomainSeoBaseline::create([
            'domain_id' => $primaryDomain->id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'baseline_type' => 'pre_rebuild',
            'captured_at' => now()->subWeeks(2),
            'source_provider' => 'matomo',
            'matomo_site_id' => '6',
            'search_console_property_uri' => 'https://moveroo.com.au/',
            'search_type' => 'web',
            'import_method' => 'matomo_api',
            'clicks' => 12,
            'impressions' => 810,
            'ctr' => 0.0148,
            'average_position' => 17.2,
            'indexed_pages' => 14,
            'not_indexed_pages' => 66,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $primaryDomain->id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'baseline_type' => 'weekly_checkpoint',
            'captured_at' => now()->subWeek(),
            'source_provider' => 'matomo',
            'matomo_site_id' => '6',
            'search_console_property_uri' => 'https://moveroo.com.au/',
            'search_type' => 'web',
            'import_method' => 'matomo_api',
            'clicks' => 18,
            'impressions' => 980,
            'ctr' => 0.0183,
            'average_position' => 15.7,
            'indexed_pages' => 19,
            'not_indexed_pages' => 58,
        ]);

        AnalyticsInstallAudit::create([
            'property_analytics_source_id' => $source->id,
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '6',
            'external_name' => 'Moveroo website',
            'expected_tracker_host' => 'stats.redirection.com.au',
            'install_verdict' => 'installed_match',
            'best_url' => 'https://moveroo.com.au/',
            'detected_site_ids' => ['6'],
            'detected_tracker_hosts' => ['stats.redirection.com.au'],
            'summary' => 'Matomo snippet detected with the expected tracker host and site ID.',
            'checked_at' => now(),
            'raw_payload' => ['verdict' => 'installed_match'],
        ]);

        DomainCheck::withoutEvents(function () use ($primaryDomain) {
            DomainCheck::factory()->create([
                'id' => (string) Str::uuid(),
                'domain_id' => $primaryDomain->id,
                'check_type' => 'uptime',
                'status' => 'ok',
                'started_at' => now()->subMinute(),
                'finished_at' => now(),
            ]);

            DomainCheck::factory()->create([
                'id' => (string) Str::uuid(),
                'domain_id' => $primaryDomain->id,
                'check_type' => 'http',
                'status' => 'ok',
                'started_at' => now()->subMinute(),
                'finished_at' => now(),
            ]);

            DomainCheck::factory()->create([
                'id' => (string) Str::uuid(),
                'domain_id' => $primaryDomain->id,
                'check_type' => 'ssl',
                'status' => 'warn',
                'started_at' => now()->subMinute(),
                'finished_at' => now(),
            ]);
        });

        DomainAlert::create([
            'domain_id' => $primaryDomain->id,
            'alert_type' => 'ssl_expiry',
            'severity' => 'warning',
            'triggered_at' => now()->subHour(),
            'auto_resolve' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties-summary');

        $response
            ->assertOk()
            ->assertJsonPath('source_system', 'domain-monitor')
            ->assertJsonPath('contract_version', 1)
            ->assertJsonPath('web_properties.0.slug', 'moveroo-website')
            ->assertJsonPath('web_properties.0.primary_domain', 'moveroo.com.au')
            ->assertJsonPath('web_properties.0.site_identity.site_name', 'Moveroo')
            ->assertJsonPath('web_properties.0.site_identity.legal_name', 'Moveroo Australia')
            ->assertJsonPath('web_properties.0.site_identity.primary_domain', 'https://moveroo.com.au/')
            ->assertJsonPath('web_properties.0.site_identity.quote_portal', 'https://wemove.moveroo.com.au/')
            ->assertJsonPath('web_properties.0.site_identity.contact_page', 'https://moveroo.com.au/contact-us')
            ->assertJsonPath('web_properties.0.platform_migration.current_platform', 'Astro')
            ->assertJsonPath('web_properties.0.platform_migration.target_platform', null)
            ->assertJsonPath('web_properties.0.platform_migration.astro_cutover_at', null)
            ->assertJsonPath('web_properties.0.canonical_origin.scheme', 'https')
            ->assertJsonPath('web_properties.0.canonical_origin.host', 'moveroo.com.au')
            ->assertJsonPath('web_properties.0.canonical_origin.base_url', 'https://moveroo.com.au')
            ->assertJsonPath('web_properties.0.canonical_origin.policy', 'known')
            ->assertJsonPath('web_properties.0.canonical_origin.scope', 'property_only')
            ->assertJsonPath('web_properties.0.canonical_origin.enforcement_eligible', true)
            ->assertJsonPath('web_properties.0.canonical_origin.excluded_subdomains.0', 'cartransport.moveroo.com.au')
            ->assertJsonPath('web_properties.0.canonical_origin.sitemap_policy_known', true)
            ->assertJsonPath('web_properties.0.repositories.0.repo_name', 'moveroo/moveroo-website-astro')
            ->assertJsonPath('web_properties.0.analytics_sources.0.external_id', '6')
            ->assertJsonPath('web_properties.0.analytics_sources.0.install_audit.install_verdict', 'installed_match')
            ->assertJsonPath('web_properties.0.event_architecture.has_contract', true)
            ->assertJsonPath('web_properties.0.event_architecture.contracts.0.contract.key', 'shared-ga4-baseline-v1')
            ->assertJsonPath('web_properties.0.event_architecture.contracts.0.rollout_status', 'defined')
            ->assertJsonPath('web_properties.0.analytics.enabled', true)
            ->assertJsonPath('web_properties.0.analytics.provider', 'matomo')
            ->assertJsonPath('web_properties.0.analytics.config.base_url', 'https://stats.redirection.com.au')
            ->assertJsonPath('web_properties.0.analytics.config.site_id', '6')
            ->assertJsonPath('web_properties.0.health_summary.checks.uptime', 'ok')
            ->assertJsonPath('web_properties.0.health_summary.checks.http', 'ok')
            ->assertJsonPath('web_properties.0.health_summary.checks.ssl', 'warn')
            ->assertJsonPath('web_properties.0.control_state', 'controlled')
            ->assertJsonPath('web_properties.0.execution_surface', 'astro_repo_controlled')
            ->assertJsonPath('web_properties.0.fleet_managed', true)
            ->assertJsonPath('web_properties.0.is_fleet_focus', true)
            ->assertJsonPath('web_properties.0.fleet_priority', $property->priority)
            ->assertJsonPath('web_properties.0.controller_repo', 'moveroo/moveroo-website-astro')
            ->assertJsonPath('web_properties.0.controller_repo_url', 'https://github.com/moveroo/moveroo-website-astro')
            ->assertJsonPath('web_properties.0.controller_local_path', '/Users/jasonhill/Projects/websites/moveroo-website-astro')
            ->assertJsonPath('web_properties.0.deployment_provider', 'vercel')
            ->assertJsonPath('web_properties.0.deployment_project_name', 'moveroo-website')
            ->assertJsonPath('web_properties.0.deployment_project_id', 'prj_moveroo123')
            ->assertJsonPath('web_properties.0.conversion_links.current.household_quote', 'https://removalists.moveroo.com.au/quote/household')
            ->assertJsonPath('web_properties.0.conversion_links.current.household_booking', 'https://removalists.moveroo.com.au/booking/create')
            ->assertJsonPath('web_properties.0.conversion_links.current.vehicle_quote', 'https://cars.moveroo.com.au/quote/v2')
            ->assertJsonPath('web_properties.0.conversion_links.current.vehicle_booking', null)
            ->assertJsonPath('web_properties.0.conversion_links.target.household_quote', 'https://quote.moveroo.com.au/household')
            ->assertJsonPath('web_properties.0.conversion_links.target.vehicle_quote', 'https://quote.moveroo.com.au/vehicle')
            ->assertJsonPath('web_properties.0.conversion_links.target.moveroo_subdomain', 'https://wemove.moveroo.com.au')
            ->assertJsonPath('web_properties.0.conversion_links.target.contact_us_page', 'https://moveroo.com.au/contact-us')
            ->assertJsonPath('web_properties.0.conversion_links.target.legacy_bookings_replacement', 'https://removalist.net/booking/create')
            ->assertJsonPath('web_properties.0.conversion_links.target.legacy_payments_replacement', 'https://wemove.moveroo.com.au/contact')
            ->assertJsonPath('web_properties.0.conversion_links.legacy_endpoints.legacy_booking_endpoint.url', 'https://wemove.moveroo.com.au/bookings')
            ->assertJsonPath('web_properties.0.conversion_links.legacy_endpoints.legacy_booking_endpoint.resolved_url', 'https://removalist.net/booking/create')
            ->assertJsonPath('web_properties.0.conversion_links.legacy_endpoints.legacy_booking_endpoint.resolved_host_changed', true)
            ->assertJsonPath('web_properties.0.conversion_links.legacy_endpoints.legacy_payment_endpoint.url', 'https://wemove.moveroo.com.au/payments')
            ->assertJsonPath('web_properties.0.conversion_links.legacy_endpoints.legacy_payment_endpoint.resolved_url', 'https://wemove.moveroo.com.au/contact')
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.has_issue_detail', false)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.issue_detail_snapshot_count', 0)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.latest_issue_detail_captured_at', null)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.has_api_enrichment', false)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.api_snapshot_count', 0)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.latest_api_captured_at', null)
            ->assertJsonPath('web_properties.0.seo_baseline_summary.has_baseline', true)
            ->assertJsonPath('web_properties.0.seo_baseline_summary.latest.baseline_type', 'weekly_checkpoint')
            ->assertJsonPath('web_properties.0.seo_baseline_summary.latest.indexed_pages', 19)
            ->assertJsonPath('web_properties.0.seo_baseline_summary.latest.not_indexed_pages', 58)
            ->assertJsonPath('web_properties.0.seo_baseline_summary.trend.point_count', 2)
            ->assertJsonPath('web_properties.0.seo_baseline_summary.trend.indexed_pages_delta', 5)
            ->assertJsonPath('web_properties.0.seo_baseline_summary.trend.not_indexed_pages_delta', -8)
            ->assertJsonPath('web_properties.0.health_summary.active_alerts_count', 1);

        $ownedSubdomains = $response->json('web_properties.0.canonical_origin.owned_subdomains');

        $this->assertIsArray($ownedSubdomains);
        $this->assertContains('www.moveroo.com.au', $ownedSubdomains);
        $this->assertContains('quoting.moveroo.com.au', $ownedSubdomains);

        /** @var array<int, array<string, mixed>> $domains */
        $domains = $response->json('web_properties.0.domains') ?? [];
        $primarySummary = collect($domains)->firstWhere('domain', 'moveroo.com.au');
        $quotingSummary = collect($domains)->firstWhere('domain', 'quoting.moveroo.com.au');

        $this->assertIsArray($primarySummary);
        $this->assertSame('send_receive', $primarySummary['email_usage']);
        $this->assertTrue($primarySummary['email_expected']);
        $this->assertTrue($primarySummary['email_sending_expected']);
        $this->assertTrue($primarySummary['email_receiving_expected']);

        $this->assertIsArray($quotingSummary);
        $this->assertSame('none', $quotingSummary['email_usage']);
        $this->assertFalse($quotingSummary['email_expected']);
        $this->assertFalse($quotingSummary['email_sending_expected']);
        $this->assertFalse($quotingSummary['email_receiving_expected']);
    }

    public function test_web_properties_summary_can_filter_to_fleet_focus_properties(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('domain_monitor.fleet_focus.tag_name', 'fleet.live');

        $fleetTag = DomainTag::firstOrCreate(
            ['name' => 'fleet.live'],
            [
                'priority' => 95,
                'color' => '#2563EB',
            ]
        );

        $fleetDomain = Domain::factory()->create([
            'domain' => 'fleet-focus.example.com',
            'is_active' => true,
        ]);

        $otherDomain = Domain::factory()->create([
            'domain' => 'non-fleet.example.com',
            'is_active' => true,
        ]);

        $fleetProperty = WebProperty::factory()->create([
            'slug' => 'fleet-focus-site',
            'name' => 'Fleet Focus Site',
            'primary_domain_id' => $fleetDomain->id,
            'priority' => 42,
        ]);

        $otherProperty = WebProperty::factory()->create([
            'slug' => 'non-fleet-site',
            'name' => 'Non Fleet Site',
            'primary_domain_id' => $otherDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $fleetProperty->id,
            'domain_id' => $fleetDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $otherProperty->id,
            'domain_id' => $otherDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $fleetDomain->tags()->syncWithoutDetaching([$fleetTag->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties-summary?fleet_focus=1');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'web_properties')
            ->assertJsonPath('web_properties.0.slug', 'fleet-focus-site')
            ->assertJsonPath('web_properties.0.is_fleet_focus', true)
            ->assertJsonPath('web_properties.0.fleet_priority', 42);

        $indexResponse = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties?fleet_focus=1');

        $indexResponse
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'fleet-focus-site');
    }

    public function test_property_apis_always_expose_fully_shaped_conversion_links_contract(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'stable-links.example.com',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'stable-links-site',
            'name' => 'Stable Links Site',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://stable-links.example.com/some-page',
            'site_identity_site_name' => null,
            'site_identity_legal_name' => null,
            'conversion_links_scanned_at' => null,
            'current_household_quote_url' => null,
            'current_household_booking_url' => null,
            'current_vehicle_quote_url' => null,
            'current_vehicle_booking_url' => null,
            'target_household_quote_url' => null,
            'target_household_booking_url' => null,
            'target_vehicle_quote_url' => null,
            'target_vehicle_booking_url' => null,
            'target_moveroo_subdomain_url' => null,
            'target_contact_us_page_url' => null,
            'target_legacy_bookings_replacement_url' => null,
            'target_legacy_payments_replacement_url' => null,
            'legacy_moveroo_endpoint_scan' => null,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $headers = [
            'Authorization' => 'Bearer test-api-key',
        ];

        $summaryResponse = $this->withHeaders($headers)->getJson('/api/web-properties-summary');

        $summaryResponse
            ->assertOk()
            ->assertJsonPath('web_properties.0.slug', 'stable-links-site')
            ->assertJsonStructure([
                'web_properties' => [[
                    'site_identity' => [
                        'site_name',
                        'legal_name',
                        'primary_domain',
                        'quote_portal',
                        'contact_page',
                    ],
                    'analytics' => [
                        'enabled',
                        'provider',
                        'config',
                    ],
                    'platform_migration' => [
                        'current_platform',
                        'target_platform',
                        'astro_cutover_at',
                    ],
                    'conversion_links' => [
                        'scanned_at',
                        'current' => [
                            'household_quote',
                            'household_booking',
                            'vehicle_quote',
                            'vehicle_booking',
                        ],
                        'target' => [
                            'household_quote',
                            'household_booking',
                            'vehicle_quote',
                            'vehicle_booking',
                            'moveroo_subdomain',
                            'contact_us_page',
                            'legacy_bookings_replacement',
                            'legacy_payments_replacement',
                        ],
                        'legacy_endpoints' => [
                            'legacy_booking_endpoint',
                            'legacy_payment_endpoint',
                        ],
                    ],
                    'seo_baseline_summary' => [
                        'has_baseline',
                        'latest' => [
                            'captured_at',
                            'baseline_type',
                            'indexed_pages',
                            'not_indexed_pages',
                            'clicks',
                            'impressions',
                            'ctr',
                            'average_position',
                        ],
                        'trend' => [
                            'window',
                            'point_count',
                            'indexed_pages_delta',
                            'not_indexed_pages_delta',
                            'points',
                        ],
                    ],
                ]],
            ])
            ->assertJsonPath('web_properties.0.site_identity.site_name', 'Stable Links')
            ->assertJsonPath('web_properties.0.site_identity.legal_name', null)
            ->assertJsonPath('web_properties.0.site_identity.primary_domain', 'https://stable-links.example.com/')
            ->assertJsonPath('web_properties.0.site_identity.quote_portal', null)
            ->assertJsonPath('web_properties.0.site_identity.contact_page', null)
            ->assertJsonPath('web_properties.0.analytics.enabled', false)
            ->assertJsonPath('web_properties.0.analytics.provider', null)
            ->assertJsonPath('web_properties.0.analytics.config', [])
            ->assertJsonPath('web_properties.0.platform_migration.current_platform', $property->platform)
            ->assertJsonPath('web_properties.0.platform_migration.target_platform', null)
            ->assertJsonPath('web_properties.0.platform_migration.astro_cutover_at', null)
            ->assertJsonPath('web_properties.0.conversion_links.scanned_at', null)
            ->assertJsonPath('web_properties.0.conversion_links.current.household_quote', null)
            ->assertJsonPath('web_properties.0.conversion_links.current.household_booking', null)
            ->assertJsonPath('web_properties.0.conversion_links.current.vehicle_quote', null)
            ->assertJsonPath('web_properties.0.conversion_links.current.vehicle_booking', null)
            ->assertJsonPath('web_properties.0.conversion_links.target.household_quote', null)
            ->assertJsonPath('web_properties.0.conversion_links.target.household_booking', null)
            ->assertJsonPath('web_properties.0.conversion_links.target.vehicle_quote', null)
            ->assertJsonPath('web_properties.0.conversion_links.target.vehicle_booking', null)
            ->assertJsonPath('web_properties.0.conversion_links.target.moveroo_subdomain', null)
            ->assertJsonPath('web_properties.0.conversion_links.target.contact_us_page', null)
            ->assertJsonPath('web_properties.0.conversion_links.target.legacy_bookings_replacement', null)
            ->assertJsonPath('web_properties.0.conversion_links.target.legacy_payments_replacement', null)
            ->assertJsonPath('web_properties.0.conversion_links.legacy_endpoints.legacy_booking_endpoint', null)
            ->assertJsonPath('web_properties.0.conversion_links.legacy_endpoints.legacy_payment_endpoint', null)
            ->assertJsonPath('web_properties.0.seo_baseline_summary.has_baseline', false)
            ->assertJsonPath('web_properties.0.seo_baseline_summary.latest.captured_at', null)
            ->assertJsonPath('web_properties.0.seo_baseline_summary.latest.baseline_type', null)
            ->assertJsonPath('web_properties.0.seo_baseline_summary.latest.indexed_pages', null)
            ->assertJsonPath('web_properties.0.seo_baseline_summary.latest.not_indexed_pages', null)
            ->assertJsonPath('web_properties.0.seo_baseline_summary.trend.window', 'last_12_checkpoints')
            ->assertJsonPath('web_properties.0.seo_baseline_summary.trend.point_count', 0)
            ->assertJsonPath('web_properties.0.seo_baseline_summary.trend.indexed_pages_delta', null)
            ->assertJsonPath('web_properties.0.seo_baseline_summary.trend.not_indexed_pages_delta', null)
            ->assertJsonPath('web_properties.0.seo_baseline_summary.trend.points', []);

        $detailResponse = $this->withHeaders($headers)->getJson('/api/web-properties/stable-links-site');

        $detailResponse
            ->assertOk()
            ->assertJsonPath('data.slug', 'stable-links-site')
            ->assertJsonStructure([
                'data' => [
                    'site_identity' => [
                        'site_name',
                        'legal_name',
                        'primary_domain',
                        'quote_portal',
                        'contact_page',
                    ],
                    'analytics' => [
                        'enabled',
                        'provider',
                        'config',
                    ],
                    'platform_migration' => [
                        'current_platform',
                        'target_platform',
                        'astro_cutover_at',
                    ],
                    'conversion_links' => [
                        'scanned_at',
                        'current' => [
                            'household_quote',
                            'household_booking',
                            'vehicle_quote',
                            'vehicle_booking',
                        ],
                        'target' => [
                            'household_quote',
                            'household_booking',
                            'vehicle_quote',
                            'vehicle_booking',
                            'moveroo_subdomain',
                            'contact_us_page',
                            'legacy_bookings_replacement',
                            'legacy_payments_replacement',
                        ],
                        'legacy_endpoints' => [
                            'legacy_booking_endpoint',
                            'legacy_payment_endpoint',
                        ],
                    ],
                    'seo_baseline_summary' => [
                        'has_baseline',
                        'latest' => [
                            'captured_at',
                            'baseline_type',
                            'indexed_pages',
                            'not_indexed_pages',
                            'clicks',
                            'impressions',
                            'ctr',
                            'average_position',
                        ],
                        'trend' => [
                            'window',
                            'point_count',
                            'indexed_pages_delta',
                            'not_indexed_pages_delta',
                            'points',
                        ],
                    ],
                ],
            ])
            ->assertJsonPath('data.site_identity.site_name', 'Stable Links')
            ->assertJsonPath('data.site_identity.legal_name', null)
            ->assertJsonPath('data.site_identity.primary_domain', 'https://stable-links.example.com/')
            ->assertJsonPath('data.site_identity.quote_portal', null)
            ->assertJsonPath('data.site_identity.contact_page', null)
            ->assertJsonPath('data.analytics.enabled', false)
            ->assertJsonPath('data.analytics.provider', null)
            ->assertJsonPath('data.analytics.config', [])
            ->assertJsonPath('data.platform_migration.current_platform', $property->platform)
            ->assertJsonPath('data.platform_migration.target_platform', null)
            ->assertJsonPath('data.platform_migration.astro_cutover_at', null)
            ->assertJsonPath('data.conversion_links.scanned_at', null)
            ->assertJsonPath('data.conversion_links.current.household_quote', null)
            ->assertJsonPath('data.conversion_links.current.household_booking', null)
            ->assertJsonPath('data.conversion_links.current.vehicle_quote', null)
            ->assertJsonPath('data.conversion_links.current.vehicle_booking', null)
            ->assertJsonPath('data.conversion_links.target.household_quote', null)
            ->assertJsonPath('data.conversion_links.target.household_booking', null)
            ->assertJsonPath('data.conversion_links.target.vehicle_quote', null)
            ->assertJsonPath('data.conversion_links.target.vehicle_booking', null)
            ->assertJsonPath('data.conversion_links.target.moveroo_subdomain', null)
            ->assertJsonPath('data.conversion_links.target.contact_us_page', null)
            ->assertJsonPath('data.conversion_links.target.legacy_bookings_replacement', null)
            ->assertJsonPath('data.conversion_links.target.legacy_payments_replacement', null)
            ->assertJsonPath('data.conversion_links.legacy_endpoints.legacy_booking_endpoint', null)
            ->assertJsonPath('data.conversion_links.legacy_endpoints.legacy_payment_endpoint', null)
            ->assertJsonPath('data.seo_baseline_summary.has_baseline', false)
            ->assertJsonPath('data.seo_baseline_summary.latest.captured_at', null)
            ->assertJsonPath('data.seo_baseline_summary.latest.baseline_type', null)
            ->assertJsonPath('data.seo_baseline_summary.latest.indexed_pages', null)
            ->assertJsonPath('data.seo_baseline_summary.latest.not_indexed_pages', null)
            ->assertJsonPath('data.seo_baseline_summary.trend.window', 'last_12_checkpoints')
            ->assertJsonPath('data.seo_baseline_summary.trend.point_count', 0)
            ->assertJsonPath('data.seo_baseline_summary.trend.indexed_pages_delta', null)
            ->assertJsonPath('data.seo_baseline_summary.trend.not_indexed_pages_delta', null)
            ->assertJsonPath('data.seo_baseline_summary.trend.points', []);
    }

    public function test_property_apis_derive_vehicle_quote_target_from_moveroo_subdomain_when_missing(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'wemove.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'wemove-website',
            'name' => 'WeMove Website',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://wemove.com.au',
            'target_vehicle_quote_url' => null,
            'target_moveroo_subdomain_url' => 'https://quotes.wemove.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $headers = [
            'Authorization' => 'Bearer test-api-key',
        ];

        $this->withHeaders($headers)
            ->getJson('/api/web-properties-summary')
            ->assertOk()
            ->assertJsonPath('web_properties.0.conversion_links.target.moveroo_subdomain', 'https://quotes.wemove.com.au')
            ->assertJsonPath('web_properties.0.conversion_links.target.vehicle_quote', 'https://quotes.wemove.com.au/quote/vehicle');

        $this->withHeaders($headers)
            ->getJson('/api/web-properties/wemove-website')
            ->assertOk()
            ->assertJsonPath('data.conversion_links.target.moveroo_subdomain', 'https://quotes.wemove.com.au')
            ->assertJsonPath('data.conversion_links.target.vehicle_quote', 'https://quotes.wemove.com.au/quote/vehicle');
    }

    public function test_property_apis_surface_latest_external_link_inventory_for_linked_domains(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'inventory.example.com',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'inventory-site',
            'name' => 'Inventory Site',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://inventory.example.com',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        DomainCheck::withoutEvents(function () use ($domain): void {
            DomainCheck::factory()->create([
                'id' => (string) Str::uuid(),
                'domain_id' => $domain->id,
                'check_type' => 'external_links',
                'status' => 'ok',
                'started_at' => now()->subMinute(),
                'finished_at' => now(),
                'duration_ms' => 1200,
                'payload' => [
                    'domain' => 'inventory.example.com',
                    'pages_scanned' => 4,
                    'external_links_count' => 2,
                    'unique_hosts_count' => 2,
                    'external_links' => [
                        [
                            'url' => 'https://quotes.inventory.example.com/start',
                            'host' => 'quotes.inventory.example.com',
                            'relationship' => 'subdomain',
                            'found_on' => 'https://inventory.example.com/',
                            'found_on_pages' => ['https://inventory.example.com/'],
                        ],
                        [
                            'url' => 'https://partner.example.org/book',
                            'host' => 'partner.example.org',
                            'relationship' => 'external',
                            'found_on' => 'https://inventory.example.com/contact',
                            'found_on_pages' => ['https://inventory.example.com/contact'],
                        ],
                    ],
                ],
                'retry_count' => 0,
            ]);
        });

        $headers = ['Authorization' => 'Bearer test-api-key'];

        $this->withHeaders($headers)
            ->getJson('/api/web-properties-summary')
            ->assertOk()
            ->assertJsonMissingPath('web_properties.0.domains.0.external_links_scan');

        $this->withHeaders($headers)
            ->getJson('/api/web-properties/inventory-site')
            ->assertOk()
            ->assertJsonPath('data.domains.0.external_links_scan.status', 'ok')
            ->assertJsonPath('data.domains.0.external_links_scan.unique_hosts_count', 2)
            ->assertJsonPath('data.domains.0.external_links_scan.external_links.1.url', 'https://partner.example.org/book');
    }

    public function test_property_detail_api_exposes_empty_external_link_inventory_shape_before_first_scan(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'inventory-empty.example.com',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'inventory-empty-site',
            'name' => 'Inventory Empty Site',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://inventory-empty.example.com',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $this->withHeaders(['Authorization' => 'Bearer test-api-key'])
            ->getJson('/api/web-properties/inventory-empty-site')
            ->assertOk()
            ->assertJsonPath('data.domains.0.external_links_scan.status', 'unknown')
            ->assertJsonPath('data.domains.0.external_links_scan.checked_at', null)
            ->assertJsonPath('data.domains.0.external_links_scan.pages_scanned', 0)
            ->assertJsonPath('data.domains.0.external_links_scan.external_links_count', 0)
            ->assertJsonPath('data.domains.0.external_links_scan.unique_hosts_count', 0)
            ->assertJsonPath('data.domains.0.external_links_scan.external_links', []);
    }

    public function test_web_properties_summary_ignores_empty_fleet_focus_filter(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('domain_monitor.fleet_focus.tag_name', 'fleet.live');

        $fleetTag = DomainTag::firstOrCreate(
            ['name' => 'fleet.live'],
            [
                'priority' => 95,
                'color' => '#2563EB',
            ]
        );

        $fleetDomain = Domain::factory()->create([
            'domain' => 'fleet-empty-filter.example.com',
            'is_active' => true,
        ]);

        $otherDomain = Domain::factory()->create([
            'domain' => 'other-empty-filter.example.com',
            'is_active' => true,
        ]);

        $fleetProperty = WebProperty::factory()->create([
            'slug' => 'fleet-empty-filter-site',
            'name' => 'Fleet Empty Filter Site',
            'primary_domain_id' => $fleetDomain->id,
        ]);

        $otherProperty = WebProperty::factory()->create([
            'slug' => 'other-empty-filter-site',
            'name' => 'Other Empty Filter Site',
            'primary_domain_id' => $otherDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $fleetProperty->id,
            'domain_id' => $fleetDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $otherProperty->id,
            'domain_id' => $otherDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $fleetDomain->tags()->syncWithoutDetaching([$fleetTag->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties-summary?fleet_focus=');

        $response
            ->assertOk()
            ->assertJsonCount(2, 'web_properties');
    }

    public function test_web_properties_summary_keeps_fleet_flag_true_for_canonical_tagged_domain_when_primary_domain_drifts(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('domain_monitor.fleet_focus.tag_name', 'fleet.live');

        $fleetTag = DomainTag::firstOrCreate(
            ['name' => 'fleet.live'],
            [
                'priority' => 95,
                'color' => '#2563EB',
            ]
        );

        $stalePrimaryDomain = Domain::factory()->create([
            'domain' => 'stale-primary.example.com',
            'is_active' => true,
        ]);

        $canonicalDomain = Domain::factory()->create([
            'domain' => 'canonical-fleet.example.com',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'canonical-fleet-site',
            'name' => 'Canonical Fleet Site',
            'primary_domain_id' => $stalePrimaryDomain->id,
            'priority' => 21,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $stalePrimaryDomain->id,
            'usage_type' => 'alias',
            'is_canonical' => false,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $canonicalDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $canonicalDomain->tags()->syncWithoutDetaching([$fleetTag->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties-summary?fleet_focus=1');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'web_properties')
            ->assertJsonPath('web_properties.0.slug', 'canonical-fleet-site')
            ->assertJsonPath('web_properties.0.is_fleet_focus', true)
            ->assertJsonPath('web_properties.0.fleet_priority', 21);
    }

    public function test_web_properties_summary_keeps_fleet_flag_true_when_primary_domain_tag_has_no_pivot_link(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('domain_monitor.fleet_focus.tag_name', 'fleet.live');

        $fleetTag = DomainTag::firstOrCreate(
            ['name' => 'fleet.live'],
            [
                'priority' => 95,
                'color' => '#2563EB',
            ]
        );

        $primaryDomain = Domain::factory()->create([
            'domain' => 'primary-only-fleet.example.com',
            'is_active' => true,
        ]);

        $canonicalDomain = Domain::factory()->create([
            'domain' => 'canonical-no-tag.example.com',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'primary-only-fleet-site',
            'name' => 'Primary Only Fleet Site',
            'primary_domain_id' => $primaryDomain->id,
            'priority' => 17,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $canonicalDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $primaryDomain->tags()->syncWithoutDetaching([$fleetTag->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties-summary?fleet_focus=1');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'web_properties')
            ->assertJsonPath('web_properties.0.slug', 'primary-only-fleet-site')
            ->assertJsonPath('web_properties.0.is_fleet_focus', true)
            ->assertJsonPath('web_properties.0.fleet_priority', 17);
    }

    public function test_web_properties_summary_prefers_any_controller_repo_with_local_path(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'secondary-controller.example.com',
            'platform' => 'WordPress',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'secondary-controller-site',
            'name' => 'Secondary Controller Site',
            'property_type' => 'website',
            'status' => 'active',
            'platform' => 'WordPress',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'secondary-controller-site',
            'repo_provider' => 'local_only',
            'local_path' => null,
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => '_wp-house',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
            'framework' => 'WordPress',
            'is_primary' => false,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties-summary');

        $response
            ->assertOk()
            ->assertJsonPath('web_properties.0.slug', 'secondary-controller-site')
            ->assertJsonPath('web_properties.0.control_state', 'controlled')
            ->assertJsonPath('web_properties.0.execution_surface', 'fleet_wordpress_controlled')
            ->assertJsonPath('web_properties.0.fleet_managed', true)
            ->assertJsonPath('web_properties.0.controller_repo', '_wp-house');
    }

    public function test_web_properties_summary_prefers_explicit_controller_repo_for_astro_surface(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'cartransport.movingagain.com.au',
            'platform' => 'Astro',
            'hosting_provider' => 'Vercel',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'cartransport-movingagain-com-au',
            'name' => 'Car Transport Moving Again',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'platform' => 'Astro',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'cartransport-new-astro',
            'repo_provider' => 'github',
            'repo_url' => 'https://github.com/iamjasonhill/cartransport-astro',
            'local_path' => '/Users/jasonhill/Projects/websites/cartransport-new-astro',
            'framework' => 'Astro',
            'is_primary' => true,
            'deployment_provider' => 'vercel',
            'deployment_project_id' => 'prj_gbL0tfky9oasyIcDr1GWj8eYOyeR',
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'moveroo/ma-catrans-program',
            'repo_provider' => 'github',
            'repo_url' => 'https://github.com/moveroo/ma-catrans-program',
            'local_path' => '/Users/jasonhill/Projects/websites/ma-car-transport-astro',
            'framework' => 'Astro',
            'is_primary' => false,
            'is_controller' => true,
            'deployment_provider' => 'vercel',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties-summary');

        $response
            ->assertOk()
            ->assertJsonPath('web_properties.0.slug', 'cartransport-movingagain-com-au')
            ->assertJsonPath('web_properties.0.controller_repo', 'moveroo/ma-catrans-program')
            ->assertJsonPath('web_properties.0.controller_repo_url', 'https://github.com/moveroo/ma-catrans-program')
            ->assertJsonPath('web_properties.0.controller_local_path', '/Users/jasonhill/Projects/websites/ma-car-transport-astro')
            ->assertJsonPath('web_properties.0.deployment_provider', 'vercel')
            ->assertJsonPath('web_properties.0.execution_surface', 'astro_repo_controlled')
            ->assertJsonPath('web_properties.0.fleet_managed', true);
    }

    public function test_web_properties_summary_can_mark_allowlisted_repository_controlled_property_as_fleet_managed(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('domain_monitor.fleet_focus.repository_controlled_domains', [
            'transportnondrivablecars.com.au',
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'transportnondrivablecars.com.au',
            'platform' => 'Custom PHP',
            'hosting_provider' => 'Synergy Wholesale PTY',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'transportnondrivablecars-com-au',
            'name' => 'transportnondrivablecars.com.au',
            'property_type' => 'website',
            'status' => 'active',
            'platform' => 'Custom PHP',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'transportnondrivablecars-com-au-php',
            'repo_provider' => 'github',
            'repo_url' => 'https://github.com/iamjasonhill/transportnondrivablecars-com-au-php',
            'local_path' => '/Users/jasonhill/Projects/websites/transportnondrivablecars-com-au-php',
            'framework' => 'Custom PHP',
            'is_primary' => true,
            'is_controller' => true,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties-summary');

        $response
            ->assertOk()
            ->assertJsonPath('web_properties.0.slug', 'transportnondrivablecars-com-au')
            ->assertJsonPath('web_properties.0.execution_surface', 'repository_controlled')
            ->assertJsonPath('web_properties.0.fleet_managed', true)
            ->assertJsonPath('web_properties.0.controller_repo', 'transportnondrivablecars-com-au-php');
    }

    public function test_web_properties_summary_surfaces_property_level_gsc_issue_detail_coverage(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'backloading-au.com.au',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'backloading-au-com-au',
            'name' => 'Backloading AU',
            'property_type' => 'website',
            'status' => 'active',
            'platform' => 'WordPress',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'captured_at' => '2026-03-31T14:58:44+00:00',
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'excluded_by_noindex',
            'captured_at' => '2026-03-31T14:32:19+00:00',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties-summary');

        $response
            ->assertOk()
            ->assertJsonPath('web_properties.0.slug', 'backloading-au-com-au')
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.has_issue_detail', true)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.issue_detail_snapshot_count', 2)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.latest_issue_detail_captured_at', '2026-03-31T14:58:44+00:00')
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.has_api_enrichment', false)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.api_snapshot_count', 0)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.latest_api_captured_at', null);
    }

    public function test_web_properties_summary_surfaces_property_level_gsc_api_enrichment_coverage(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'api-enriched.example.au',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'api-enriched-site',
            'name' => 'API Enriched Site',
            'property_type' => 'website',
            'status' => 'active',
            'platform' => 'WordPress',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'blocked_by_robots_in_indexing',
            'capture_method' => 'gsc_api',
            'captured_at' => '2026-04-01T03:41:19+00:00',
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'page_with_redirect_in_sitemap',
            'capture_method' => 'gsc_mcp_api',
            'captured_at' => '2026-04-01T05:12:02+00:00',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties-summary');

        $response
            ->assertOk()
            ->assertJsonPath('web_properties.0.slug', 'api-enriched-site')
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.has_issue_detail', false)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.issue_detail_snapshot_count', 0)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.latest_issue_detail_captured_at', null)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.has_api_enrichment', true)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.api_snapshot_count', 2)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.latest_api_captured_at', '2026-04-01T05:12:02+00:00');
    }

    public function test_web_property_health_summary_endpoint_returns_property_health(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'moving-again.com.au',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'moving-again',
            'name' => 'Moving Again',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        DomainCheck::withoutEvents(function () use ($domain) {
            DomainCheck::factory()->create([
                'id' => (string) Str::uuid(),
                'domain_id' => $domain->id,
                'check_type' => 'dns',
                'status' => 'fail',
                'started_at' => now()->subMinute(),
                'finished_at' => now(),
            ]);
        });

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/web-properties/moving-again/health-summary');

        $response
            ->assertOk()
            ->assertJsonPath('data.slug', 'moving-again')
            ->assertJsonPath('data.health_summary.primary_domain', 'moving-again.com.au')
            ->assertJsonPath('data.health_summary.overall_status', 'fail')
            ->assertJsonPath('data.health_summary.checks.dns', 'fail');
    }
}
