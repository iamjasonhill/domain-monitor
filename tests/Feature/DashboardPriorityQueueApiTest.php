<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\DomainSeoBaseline;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPriorityQueueApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_priority_queue_endpoint_returns_actionable_domains_only(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $mustFixDomain = Domain::factory()->create([
            'domain' => 'must-fix.example.com',
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
            'expires_at' => null,
        ]);

        $mustFixProperty = WebProperty::factory()->create([
            'slug' => 'must-fix-site',
            'name' => 'Must Fix Site',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $mustFixDomain->id,
            'canonical_origin_scheme' => 'https',
            'canonical_origin_host' => 'must-fix.example.com',
            'canonical_origin_policy' => 'known',
            'canonical_origin_enforcement_eligible' => true,
            'target_household_quote_url' => 'https://quote.must-fix.example.com/household',
            'target_moveroo_subdomain_url' => 'https://must-fix.moveroo.com.au',
            'target_contact_us_page_url' => 'https://must-fix.example.com/contact-us',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $mustFixProperty->id,
            'domain_id' => $mustFixDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $mustFixProperty->id,
            'repo_name' => 'must-fix-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/must-fix-site',
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $mustFixDomain->id,
            'check_type' => 'http',
            'status' => 'fail',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $mustFixDomain->id,
            'check_type' => 'email_security',
            'status' => 'warn',
        ]);

        $shouldFixDomain = Domain::factory()->create([
            'domain' => 'should-fix.example.com',
            'is_active' => true,
            'platform' => 'Astro',
            'hosting_provider' => 'Vercel',
            'expires_at' => null,
        ]);

        $shouldFixProperty = WebProperty::factory()->create([
            'slug' => 'should-fix-site',
            'name' => 'Should Fix Site',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => $shouldFixDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $shouldFixProperty->id,
            'domain_id' => $shouldFixDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $shouldFixProperty->id,
            'repo_name' => 'should-fix-site-astro',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/should-fix-site-astro',
            'framework' => 'Astro',
            'is_primary' => true,
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $shouldFixDomain->id,
            'check_type' => 'security_headers',
            'status' => 'warn',
        ]);

        $coverageGapDomain = Domain::factory()->create([
            'domain' => 'coverage-gap.example.com',
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'Synergy Wholesale PTY',
            'expires_at' => null,
        ]);

        $coverageGapProperty = WebProperty::factory()->create([
            'slug' => 'coverage-gap-site',
            'name' => 'Coverage Gap Site',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $coverageGapDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $coverageGapProperty->id,
            'domain_id' => $coverageGapDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $parkedDomain = Domain::factory()->create([
            'domain' => 'parked.example.com',
            'is_active' => true,
            'dns_config_name' => 'Parked',
            'expires_at' => null,
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $parkedDomain->id,
            'check_type' => 'http',
            'status' => 'fail',
        ]);

        $emailOnlyDomain = Domain::factory()->create([
            'domain' => 'mail-only.example.com',
            'is_active' => true,
            'platform' => 'Email Only',
            'expires_at' => null,
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $emailOnlyDomain->id,
            'check_type' => 'http',
            'status' => 'fail',
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $emailOnlyDomain->id,
            'check_type' => 'email_security',
            'status' => 'warn',
        ]);

        $emailOnlyProperty = WebProperty::factory()->create([
            'slug' => 'mail-only-site',
            'name' => 'Mail Only Site',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $emailOnlyDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $emailOnlyProperty->id,
            'domain_id' => $emailOnlyDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $emailOnlyProperty->id,
            'repo_name' => 'mail-only-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/mail-only-site',
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $emailOnlyDomain->id,
            'web_property_id' => $emailOnlyProperty->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '99',
            'search_console_property_uri' => 'sc-domain:mail-only.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => 0,
            'average_position' => 0,
            'indexed_pages' => 1,
            'not_indexed_pages' => 3,
            'pages_with_redirect' => 4,
            'raw_payload' => ['issues' => [['label' => 'Page with redirect', 'count' => 4]]],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue');

        $response
            ->assertOk()
            ->assertJsonPath('source_system', 'domain-monitor-priority-queue')
            ->assertJsonPath('contract_version', 2)
            ->assertJsonPath('stats.must_fix', 1)
            ->assertJsonPath('stats.should_fix', 3)
            ->assertJsonPath('must_fix.0.domain', 'must-fix.example.com')
            ->assertJsonPath('must_fix.0.hosting_provider', 'DreamIT Host')
            ->assertJsonPath('must_fix.0.issue_family', 'health.http')
            ->assertJsonPath('must_fix.0.control_id', 'transport.http_health')
            ->assertJsonPath('must_fix.0.platform_profile', 'wordpress_legacy_unmanaged')
            ->assertJsonPath('must_fix.0.canonical_origin.base_url', 'https://must-fix.example.com')
            ->assertJsonPath('must_fix.0.canonical_origin.policy', 'known')
            ->assertJsonPath('must_fix.0.canonical_origin.scope', 'property_only')
            ->assertJsonPath('must_fix.0.canonical_origin.enforcement_eligible', true)
            ->assertJsonPath('must_fix.0.conversion_links.target.household_quote', 'https://quote.must-fix.example.com/household')
            ->assertJsonPath('must_fix.0.conversion_links.target.moveroo_subdomain', 'https://must-fix.moveroo.com.au')
            ->assertJsonPath('must_fix.0.conversion_links.target.contact_us_page', 'https://must-fix.example.com/contact-us')
            ->assertJsonPath('must_fix.0.rollout_scope', 'domain_only')
            ->assertJsonPath('must_fix.0.is_standard_gap', false);

        $payload = $response->json();

        $this->assertIsArray($payload);
        $this->assertGreaterThanOrEqual(1, (int) data_get($payload, 'derived.standard_gap_candidates', 0));
        $this->assertGreaterThanOrEqual(1, (int) data_get($payload, 'derived.coverage_gap_candidates', 0));

        /** @var array<int, array<string, mixed>> $mustFixPayload */
        $mustFixPayload = data_get($payload, 'must_fix', []);
        /** @var array<int, array<string, mixed>> $shouldFixPayload */
        $shouldFixPayload = data_get($payload, 'should_fix', []);
        $mustFixDomains = collect($mustFixPayload);
        $shouldFixDomains = collect($shouldFixPayload);

        $this->assertFalse($mustFixDomains->contains(fn (array $item): bool => $item['domain'] === 'parked.example.com'));
        $this->assertFalse($mustFixDomains->contains(fn (array $item): bool => $item['domain'] === 'mail-only.example.com'));
        $this->assertTrue($shouldFixDomains->contains(fn (array $item): bool => $item['domain'] === 'mail-only.example.com'));
        $this->assertTrue($shouldFixDomains->contains(fn (array $item): bool => $item['domain'] === 'should-fix.example.com'));
        $this->assertTrue($shouldFixDomains->contains(fn (array $item): bool => $item['domain'] === 'coverage-gap.example.com'));

        $emailOnly = $shouldFixDomains->firstWhere('domain', 'mail-only.example.com');

        $this->assertIsArray($emailOnly);
        $this->assertTrue($emailOnly['is_email_only']);
        $this->assertSame(['Email security needs review'], $emailOnly['primary_reasons']);

        $astroShouldFix = $shouldFixDomains->firstWhere('domain', 'should-fix.example.com');

        $this->assertIsArray($astroShouldFix);
        $this->assertSame('security.headers_baseline', $astroShouldFix['issue_family']);
        $this->assertSame('security.headers_baseline', $astroShouldFix['control_id']);
        $this->assertSame('astro_marketing_managed', $astroShouldFix['platform_profile']);
        $this->assertSame('vercel_astro', $astroShouldFix['host_profile']);
        $this->assertSame('astro_core', $astroShouldFix['control_profile']);
        $this->assertSame('shared_astro_repo_conventions_and_host_config', $astroShouldFix['baseline_surface']);
        $this->assertSame('fleet', $astroShouldFix['rollout_scope']);
        $this->assertTrue($astroShouldFix['is_standard_gap']);

        $coverageGap = $shouldFixDomains->firstWhere('domain', 'coverage-gap.example.com');

        $this->assertIsArray($coverageGap);
        $this->assertSame('control.coverage_required', $coverageGap['issue_family']);
        $this->assertSame('control.website_fleet_coverage', $coverageGap['control_id']);
        $this->assertTrue($coverageGap['coverage_required']);
        $this->assertSame('missing_repository', $coverageGap['coverage_status']);
        $this->assertTrue($coverageGap['coverage_gap']);
        $this->assertSame(['Fleet controller access is missing'], $coverageGap['primary_reasons']);
        $this->assertSame('domain_only', $coverageGap['rollout_scope']);
        $this->assertFalse($coverageGap['is_standard_gap']);
    }

    public function test_priority_queue_prefers_canonical_property_for_domain_metadata(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'canonical-choice.example.com',
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
        ]);

        $alphabeticalNonCanonical = WebProperty::factory()->create([
            'slug' => 'alpha-site',
            'name' => 'Alpha Site',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $alphabeticalNonCanonical->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => false,
        ]);

        $canonicalProperty = WebProperty::factory()->create([
            'slug' => 'zeta-site',
            'name' => 'Zeta Site',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $canonicalProperty->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $canonicalProperty->id,
            'repo_name' => 'zeta-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/zeta-site',
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $domain->id,
            'check_type' => 'security_headers',
            'status' => 'warn',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue');

        $response->assertOk()->assertJsonPath('contract_version', 2);

        /** @var array<int, array<string, mixed>> $shouldFixPayload */
        $shouldFixPayload = $response->json('should_fix') ?? [];
        $shouldFix = collect($shouldFixPayload)->firstWhere('domain', 'canonical-choice.example.com');

        $this->assertIsArray($shouldFix);
        $this->assertSame('zeta-site', $shouldFix['web_property_slug']);
        $this->assertSame('Zeta Site', $shouldFix['web_property_name']);
        $this->assertSame('controlled', $shouldFix['coverage_status']);
        $this->assertFalse($shouldFix['coverage_gap']);
    }

    public function test_priority_queue_skips_email_security_issues_for_web_only_subdomains(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $primaryDomain = Domain::factory()->create([
            'domain' => 'backloading-au.com.au',
            'is_active' => true,
            'platform' => 'WordPress',
        ]);

        $quotingSubdomain = Domain::factory()->create([
            'domain' => 'quoting.backloading-au.com.au',
            'is_active' => true,
            'platform' => 'WordPress',
            'email_usage' => Domain::EMAIL_USAGE_NONE,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'backloading-au-com-au',
            'name' => 'Backloading AU',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $primaryDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $quotingSubdomain->id,
            'usage_type' => 'subdomain',
            'is_canonical' => false,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'backloading-au',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/backloading-au',
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $quotingSubdomain->id,
            'check_type' => 'email_security',
            'status' => 'fail',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue');

        $response->assertOk();

        /** @var array<int, array<string, mixed>> $mustFixPayload */
        $mustFixPayload = $response->json('must_fix') ?? [];
        /** @var array<int, array<string, mixed>> $shouldFixPayload */
        $shouldFixPayload = $response->json('should_fix') ?? [];

        $this->assertFalse(collect($mustFixPayload)->contains(
            fn (array $item): bool => ($item['domain'] ?? null) === 'quoting.backloading-au.com.au'
        ));
        $this->assertFalse(collect($shouldFixPayload)->contains(
            fn (array $item): bool => ($item['domain'] ?? null) === 'quoting.backloading-au.com.au'
        ));
    }

    public function test_priority_queue_skips_domains_linked_only_as_subdomains(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $primaryDomain = Domain::factory()->create([
            'domain' => 'backloadingremovals.com.au',
            'is_active' => true,
            'platform' => 'WordPress',
        ]);

        $linkedSubdomain = Domain::factory()->create([
            'domain' => 'vehicles.backloadingremovals.com.au',
            'is_active' => true,
            'platform' => 'WordPress',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'backloadingremovals-com-au',
            'name' => 'Backloading Removals',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $primaryDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $linkedSubdomain->id,
            'usage_type' => 'subdomain',
            'is_canonical' => false,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'backloadingremovals',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/backloadingremovals',
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $linkedSubdomain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '15',
            'search_console_property_uri' => 'sc-domain:vehicles.backloadingremovals.com.au',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => 0,
            'average_position' => 0,
            'pages_with_redirect' => 56,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Page with redirect', 'count' => 56],
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue');

        $response->assertOk();

        /** @var array<int, array<string, mixed>> $mustFixPayload */
        $mustFixPayload = $response->json('must_fix') ?? [];
        /** @var array<int, array<string, mixed>> $shouldFixPayload */
        $shouldFixPayload = $response->json('should_fix') ?? [];

        $this->assertFalse(collect($mustFixPayload)->contains(
            fn (array $item): bool => ($item['domain'] ?? null) === 'vehicles.backloadingremovals.com.au'
        ));
        $this->assertFalse(collect($shouldFixPayload)->contains(
            fn (array $item): bool => ($item['domain'] ?? null) === 'vehicles.backloadingremovals.com.au'
        ));
    }

    public function test_priority_queue_promotes_page_with_redirect_baseline_issue_into_fleet_standard_gap(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        $standards = config('domain_monitor.priority_queue_standards', []);
        $controls = is_array(data_get($standards, 'controls')) ? data_get($standards, 'controls') : [];
        $controls['seo.robots_and_sitemap_consistency'] = [
            'issue_families' => [
                'page_with_redirect_in_sitemap',
            ],
        ];
        $standards['controls'] = $controls;
        config()->set('domain_monitor.priority_queue_standards', $standards);

        $domain = Domain::factory()->create([
            'domain' => 'redirect-gap.example.com',
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'Synergy Wholesale PTY',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'redirect-gap-site',
            'name' => 'Redirect Gap Site',
            'property_type' => 'website',
            'status' => 'active',
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
            'repo_name' => '_wp-house',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/_wp-house',
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '15',
            'search_console_property_uri' => 'sc-domain:redirect-gap.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => 0,
            'average_position' => 0,
            'indexed_pages' => 12,
            'not_indexed_pages' => 18,
            'pages_with_redirect' => 7,
            'raw_payload' => ['issues' => [['label' => 'Page with redirect', 'count' => 7]]],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue');

        $response->assertOk()->assertJsonPath('contract_version', 2);

        /** @var array<int, array<string, mixed>> $mustFixPayload */
        $mustFixPayload = $response->json('must_fix') ?? [];
        $mustFix = collect($mustFixPayload)->firstWhere('domain', 'redirect-gap.example.com');

        $this->assertIsArray($mustFix);
        $this->assertContains('Search Console reports page with redirect (7 URLs)', $mustFix['primary_reasons']);
        $this->assertSame('page_with_redirect_in_sitemap', $mustFix['issue_family']);
        $this->assertContains('page_with_redirect_in_sitemap', $mustFix['issue_families']);
        $this->assertSame('seo.robots_and_sitemap_consistency', $mustFix['control_id']);
        $this->assertSame('wordpress_house_managed', $mustFix['platform_profile']);
        $this->assertSame('ventra_litespeed_wordpress', $mustFix['host_profile']);
        $this->assertSame('leadgen_wordpress_core', $mustFix['control_profile']);
        $this->assertSame('shared_wordpress_house_and_live_host_config', $mustFix['baseline_surface']);
        $this->assertSame('fleet', $mustFix['rollout_scope']);
        $this->assertTrue($mustFix['is_standard_gap']);
    }

    public function test_priority_queue_promotes_blocked_by_robots_and_duplicate_canonical_baseline_issues(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $blockedDomain = Domain::factory()->create([
            'domain' => 'blocked-by-robots.example.com',
            'is_active' => true,
            'platform' => 'Astro',
            'hosting_provider' => 'Vercel',
            'expires_at' => null,
        ]);

        $blockedProperty = WebProperty::factory()->create([
            'slug' => 'blocked-by-robots-site',
            'name' => 'Blocked By Robots Site',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => $blockedDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $blockedProperty->id,
            'domain_id' => $blockedDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $blockedProperty->id,
            'repo_name' => 'blocked-by-robots-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/blocked-by-robots-site',
            'framework' => 'Astro',
            'is_primary' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $blockedDomain->id,
            'web_property_id' => $blockedProperty->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '99',
            'search_console_property_uri' => 'sc-domain:blocked-by-robots.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_plus_manual_csv',
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => 0,
            'average_position' => 0,
            'indexed_pages' => 50,
            'not_indexed_pages' => 8,
            'blocked_by_robots' => 3,
            'duplicate_without_user_selected_canonical' => 11,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Blocked by robots.txt', 'count' => 3],
                    ['label' => 'Duplicate without user-selected canonical', 'count' => 11],
                ],
            ],
        ]);

        $canonicalDomain = Domain::factory()->create([
            'domain' => 'duplicate-canonical.example.com',
            'is_active' => true,
            'platform' => 'Astro',
            'hosting_provider' => 'Vercel',
            'expires_at' => null,
        ]);

        $canonicalProperty = WebProperty::factory()->create([
            'slug' => 'duplicate-canonical-site',
            'name' => 'Duplicate Canonical Site',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => $canonicalDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $canonicalProperty->id,
            'domain_id' => $canonicalDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $canonicalProperty->id,
            'repo_name' => 'duplicate-canonical-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/duplicate-canonical-site',
            'framework' => 'Astro',
            'is_primary' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $canonicalDomain->id,
            'web_property_id' => $canonicalProperty->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '100',
            'search_console_property_uri' => 'sc-domain:duplicate-canonical.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_plus_manual_csv',
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => 0,
            'average_position' => 0,
            'indexed_pages' => 50,
            'not_indexed_pages' => 8,
            'duplicate_without_user_selected_canonical' => 11,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Duplicate without user-selected canonical', 'count' => 11],
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue');

        $response->assertOk()->assertJsonPath('contract_version', 2);

        /** @var array<int, array<string, mixed>> $mustFixPayload */
        $mustFixPayload = $response->json('must_fix') ?? [];
        /** @var array<int, array<string, mixed>> $shouldFixPayload */
        $shouldFixPayload = $response->json('should_fix') ?? [];
        $mustFix = collect($mustFixPayload)->firstWhere('domain', 'blocked-by-robots.example.com');
        $shouldFix = collect($shouldFixPayload)->firstWhere('domain', 'duplicate-canonical.example.com');

        $this->assertIsArray($mustFix);
        $this->assertContains('Search Console reports blocked by robots.txt (3 URLs)', $mustFix['primary_reasons']);
        $this->assertContains('blocked_by_robots_in_indexing', $mustFix['issue_families']);
        $this->assertSame('blocked_by_robots_in_indexing', $mustFix['issue_family']);
        $this->assertSame('seo.robots_and_sitemap_consistency', $mustFix['control_id']);
        $this->assertSame('fleet', $mustFix['rollout_scope']);

        $this->assertIsArray($shouldFix);
        $this->assertContains('Search Console reports duplicate without user-selected canonical (11 URLs)', $shouldFix['primary_reasons']);
        $this->assertContains('duplicate_without_user_selected_canonical', $shouldFix['issue_families']);
        $this->assertSame('duplicate_without_user_selected_canonical', $shouldFix['issue_family']);
        $this->assertSame('seo.canonical_consistency', $shouldFix['control_id']);
        $this->assertSame('fleet', $shouldFix['rollout_scope']);
    }

    public function test_priority_queue_keeps_primary_transport_failure_as_canonical_issue_family(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'mixed-priority.example.com',
            'is_active' => true,
            'platform' => 'Astro',
            'hosting_provider' => 'Vercel',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'mixed-priority-site',
            'name' => 'Mixed Priority Site',
            'property_type' => 'marketing_site',
            'status' => 'active',
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
            'repo_name' => 'mixed-priority-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/mixed-priority-site',
            'framework' => 'Astro',
            'is_primary' => true,
        ]);

        DomainCheck::factory()->create([
            'domain_id' => $domain->id,
            'check_type' => 'http',
            'status' => 'fail',
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '101',
            'search_console_property_uri' => 'sc-domain:mixed-priority.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'blocked_by_robots' => 5,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Blocked by robots.txt', 'count' => 5],
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue');

        $response->assertOk()->assertJsonPath('contract_version', 2);

        /** @var array<int, array<string, mixed>> $mustFixPayload */
        $mustFixPayload = $response->json('must_fix') ?? [];
        $mustFix = collect($mustFixPayload)->firstWhere('domain', 'mixed-priority.example.com');

        $this->assertIsArray($mustFix);
        $this->assertContains('HTTP check is failing', $mustFix['primary_reasons']);
        $this->assertContains('Search Console reports blocked by robots.txt (5 URLs)', $mustFix['primary_reasons']);
        $this->assertSame('health.http', $mustFix['issue_family']);
        $this->assertSame('transport.http_health', $mustFix['control_id']);
        $this->assertContains('blocked_by_robots_in_indexing', $mustFix['issue_families']);
    }

    public function test_priority_queue_hides_intentional_wordpress_admin_robots_exclusion(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'intentional-admin-queue.example.com',
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
            'expires_at' => null,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'intentional-admin-queue-site',
            'name' => 'Intentional Admin Queue Site',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        DomainCheck::create([
            'domain_id' => $domain->id,
            'check_type' => 'seo',
            'status' => 'ok',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'duration_ms' => 100,
            'payload' => [
                'results' => [
                    'robots' => [
                        'url' => 'https://intentional-admin-queue.example.com/robots.txt',
                        'has_standard_wordpress_admin_rule' => true,
                        'allow_admin_ajax' => true,
                    ],
                ],
            ],
            'retry_count' => 0,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'intentional-admin-queue-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/intentional-admin-queue-site',
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '77',
            'search_console_property_uri' => 'sc-domain:intentional-admin-queue.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_plus_manual_csv',
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => 0,
            'average_position' => 0,
            'indexed_pages' => 10,
            'not_indexed_pages' => 1,
            'blocked_by_robots' => 1,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Blocked by robots.txt', 'count' => 1],
                ],
            ],
        ]);

        \App\Models\SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'blocked_by_robots_in_indexing',
            'source_issue_label' => 'Blocked by robots.txt',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:intentional-admin-queue.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => ['https://intentional-admin-queue.example.com/wp-admin/'],
            'examples' => [
                ['url' => 'https://intentional-admin-queue.example.com/wp-admin/', 'last_crawled' => now()->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => ['https://intentional-admin-queue.example.com/wp-admin/'],
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue');

        $response->assertOk();
        /** @var array<int, array<string, mixed>> $mustFixPayload */
        $mustFixPayload = $response->json('must_fix') ?? [];
        /** @var array<int, array<string, mixed>> $shouldFixPayload */
        $shouldFixPayload = $response->json('should_fix') ?? [];

        $this->assertNull(collect($mustFixPayload)->firstWhere('domain', 'intentional-admin-queue.example.com'));
        $this->assertNull(collect($shouldFixPayload)->firstWhere('domain', 'intentional-admin-queue.example.com'));
    }

    public function test_priority_queue_keeps_admin_robots_issue_when_stored_seo_check_does_not_confirm_wordpress_rule(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'non-standard-admin-queue.example.com',
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
            'expires_at' => null,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'non-standard-admin-queue-site',
            'name' => 'Non Standard Admin Queue Site',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        DomainCheck::create([
            'domain_id' => $domain->id,
            'check_type' => 'seo',
            'status' => 'ok',
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'duration_ms' => 100,
            'payload' => [
                'results' => [
                    'robots' => [
                        'url' => 'https://non-standard-admin-queue.example.com/robots.txt',
                        'has_standard_wordpress_admin_rule' => false,
                        'allow_admin_ajax' => false,
                    ],
                ],
            ],
            'retry_count' => 0,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'non-standard-admin-queue-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/non-standard-admin-queue-site',
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '78',
            'search_console_property_uri' => 'sc-domain:non-standard-admin-queue.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_plus_manual_csv',
            'clicks' => 0,
            'impressions' => 0,
            'ctr' => 0,
            'average_position' => 0,
            'indexed_pages' => 10,
            'not_indexed_pages' => 1,
            'blocked_by_robots' => 1,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Blocked by robots.txt', 'count' => 1],
                ],
            ],
        ]);

        \App\Models\SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'blocked_by_robots_in_indexing',
            'source_issue_label' => 'Blocked by robots.txt',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:non-standard-admin-queue.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => ['https://non-standard-admin-queue.example.com/wp-admin/'],
            'examples' => [
                ['url' => 'https://non-standard-admin-queue.example.com/wp-admin/', 'last_crawled' => now()->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => ['https://non-standard-admin-queue.example.com/wp-admin/'],
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue');

        $response->assertOk();
        /** @var array<int, array<string, mixed>> $mustFixPayload */
        $mustFixPayload = $response->json('must_fix') ?? [];

        $mustFix = collect($mustFixPayload)->firstWhere('domain', 'non-standard-admin-queue.example.com');

        $this->assertIsArray($mustFix);
        $this->assertContains('blocked_by_robots_in_indexing', $mustFix['issue_families']);
    }

    public function test_priority_queue_rebuilds_search_console_reason_counts_from_filtered_404_examples(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'filtered-404-queue.example.com',
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
            'expires_at' => null,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'filtered-404-queue-site',
            'name' => 'Filtered 404 Queue Site',
            'property_type' => 'website',
            'status' => 'active',
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
            'repo_name' => 'filtered-404-queue-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/filtered-404-queue-site',
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '790',
            'search_console_property_uri' => 'sc-domain:filtered-404-queue.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_plus_manual_csv',
            'not_found_404' => 2,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Not found (404)', 'count' => 2],
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'not_found_404',
            'source_issue_label' => 'Not found (404)',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:filtered-404-queue.example.com',
            'captured_at' => now()->subDay(),
            'captured_by' => 'test',
            'affected_url_count' => 2,
            'sample_urls' => [
                'https://filtered-404-queue.example.com/fixed-page/',
                'https://filtered-404-queue.example.com/still-missing/',
            ],
            'examples' => [
                ['url' => 'https://filtered-404-queue.example.com/fixed-page/', 'last_crawled' => now()->subDays(2)->toDateString()],
                ['url' => 'https://filtered-404-queue.example.com/still-missing/', 'last_crawled' => now()->subDays(2)->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://filtered-404-queue.example.com/fixed-page/',
                    'https://filtered-404-queue.example.com/still-missing/',
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'not_found_404',
            'source_issue_label' => 'Not found (404)',
            'capture_method' => 'gsc_live_recheck',
            'source_report' => 'search_console_live_http_recheck',
            'source_property' => 'sc-domain:filtered-404-queue.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 2,
            'sample_urls' => [
                'https://filtered-404-queue.example.com/fixed-page/',
                'https://filtered-404-queue.example.com/still-missing/',
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://filtered-404-queue.example.com/fixed-page/',
                    'https://filtered-404-queue.example.com/still-missing/',
                ],
                'live_url_checks' => [
                    [
                        'url' => 'https://filtered-404-queue.example.com/fixed-page/',
                        'checked_at' => now()->toIso8601String(),
                        'final_url' => 'https://filtered-404-queue.example.com/fixed-page/',
                        'final_status' => 200,
                        'resolved_ok' => true,
                        'host_changed' => false,
                    ],
                    [
                        'url' => 'https://filtered-404-queue.example.com/still-missing/',
                        'checked_at' => now()->toIso8601String(),
                        'final_url' => 'https://filtered-404-queue.example.com/still-missing/',
                        'final_status' => 404,
                        'resolved_ok' => false,
                        'host_changed' => false,
                    ],
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue');

        $response->assertOk();
        /** @var array<int, array<string, mixed>> $mustFixPayload */
        $mustFixPayload = $response->json('must_fix') ?? [];
        /** @var array<int, array<string, mixed>> $shouldFixPayload */
        $shouldFixPayload = $response->json('should_fix') ?? [];
        $queueItem = collect([...$mustFixPayload, ...$shouldFixPayload])->firstWhere('domain', 'filtered-404-queue.example.com');

        $this->assertIsArray($queueItem);
        $this->assertContains('Search Console reports not found (404) (1 URLs)', $queueItem['primary_reasons']);
        $this->assertSame('Search Console reports not found (404) (1 URLs)', data_get($queueItem, 'issue_entries.0.reason'));
    }

    public function test_priority_queue_suppresses_resolved_legacy_payment_404_when_queue_evidence_uses_slim_property_select(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $domain = Domain::factory()->create([
            'domain' => 'resolved-legacy-queue.example.com',
            'is_active' => true,
            'platform' => 'WordPress',
            'hosting_provider' => 'DreamIT Host',
            'expires_at' => null,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'resolved-legacy-queue-site',
            'name' => 'Resolved Legacy Queue Site',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
            'target_moveroo_subdomain_url' => 'https://quotes.resolved-legacy-queue.example.com',
            'target_legacy_payments_replacement_url' => 'https://quotes.resolved-legacy-queue.example.com/contact',
            'legacy_moveroo_endpoint_scan' => [
                'legacy_payment_endpoint' => [
                    'classification' => 'legacy_payment_endpoint',
                    'found_on' => 'https://resolved-legacy-queue.example.com/',
                    'url' => 'https://quotes.resolved-legacy-queue.example.com/payments',
                    'resolved_url' => 'https://quotes.resolved-legacy-queue.example.com/contact',
                    'resolved_status' => 200,
                    'resolved_host_changed' => false,
                ],
            ],
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'resolved-legacy-queue-site',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/resolved-legacy-queue-site',
            'framework' => 'WordPress',
            'is_primary' => true,
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'captured_by' => 'test',
            'source_provider' => 'matomo',
            'matomo_site_id' => '610',
            'search_console_property_uri' => 'sc-domain:resolved-legacy-queue.example.com',
            'search_type' => 'web',
            'date_range_start' => now()->subDays(28)->toDateString(),
            'date_range_end' => now()->toDateString(),
            'import_method' => 'matomo_api',
            'not_found_404' => 1,
            'raw_payload' => [
                'issues' => [
                    ['label' => 'Not found (404)', 'count' => 1],
                ],
            ],
        ]);

        SearchConsoleIssueSnapshot::factory()->create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'issue_class' => 'not_found_404',
            'source_issue_label' => 'Not found (404)',
            'capture_method' => 'gsc_drilldown_zip',
            'source_report' => 'search_console_page_indexing_drilldown',
            'source_property' => 'sc-domain:resolved-legacy-queue.example.com',
            'captured_at' => now(),
            'captured_by' => 'test',
            'affected_url_count' => 1,
            'sample_urls' => [
                'https://quotes.resolved-legacy-queue.example.com/payments',
            ],
            'examples' => [
                ['url' => 'https://quotes.resolved-legacy-queue.example.com/payments', 'last_crawled' => now()->subDays(1)->toDateString()],
            ],
            'normalized_payload' => [
                'affected_urls' => [
                    'https://quotes.resolved-legacy-queue.example.com/payments',
                ],
            ],
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/dashboard/priority-queue');

        $response->assertOk();

        /** @var array<int, array<string, mixed>> $mustFixPayload */
        $mustFixPayload = $response->json('must_fix') ?? [];
        /** @var array<int, array<string, mixed>> $shouldFixPayload */
        $shouldFixPayload = $response->json('should_fix') ?? [];
        $queueItem = collect([...$mustFixPayload, ...$shouldFixPayload])->first(function (array $item): bool {
            return ($item['property_slug'] ?? null) === 'resolved-legacy-queue-site'
                && ($item['issue_family'] ?? null) === 'search_console.not_found_404';
        });

        $this->assertNull($queueItem);
    }
}
