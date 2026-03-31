<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainCheck;
use App\Models\DomainSeoBaseline;
use App\Models\PropertyRepository;
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
            ->assertJsonPath('must_fix.0.rollout_scope', 'domain_only')
            ->assertJsonPath('must_fix.0.is_standard_gap', false);

        $payload = $response->json();

        $this->assertIsArray($payload);
        $this->assertGreaterThanOrEqual(1, (int) data_get($payload, 'derived.standard_gap_candidates', 0));
        $this->assertGreaterThanOrEqual(1, (int) data_get($payload, 'derived.coverage_gap_candidates', 0));

        $mustFixDomains = collect(data_get($payload, 'must_fix', []));
        $shouldFixDomains = collect(data_get($payload, 'should_fix', []));

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

        $shouldFix = collect($response->json('should_fix'))->firstWhere('domain', 'canonical-choice.example.com');

        $this->assertIsArray($shouldFix);
        $this->assertSame('zeta-site', $shouldFix['web_property_slug']);
        $this->assertSame('Zeta Site', $shouldFix['web_property_name']);
        $this->assertSame('controlled', $shouldFix['coverage_status']);
        $this->assertFalse($shouldFix['coverage_gap']);
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

        $mustFix = collect($response->json('must_fix'))->firstWhere('domain', 'redirect-gap.example.com');

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
}
