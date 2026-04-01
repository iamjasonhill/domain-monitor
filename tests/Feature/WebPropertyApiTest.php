<?php

namespace Tests\Feature;

use App\Models\AnalyticsInstallAudit;
use App\Models\Domain;
use App\Models\DomainAlert;
use App\Models\DomainCheck;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        $property = WebProperty::factory()->create([
            'slug' => 'moveroo-website',
            'name' => 'Moveroo Website',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'platform' => 'Astro',
            'primary_domain_id' => $primaryDomain->id,
            'production_url' => 'https://moveroo.com.au',
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

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'moveroo-website-astro',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/moveroo-website-astro',
            'framework' => 'Astro',
            'is_primary' => true,
        ]);

        PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '6',
            'external_name' => 'Moveroo website',
            'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
            'is_primary' => true,
        ]);

        $source = PropertyAnalyticsSource::query()->where('web_property_id', $property->id)->firstOrFail();

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
            DB::table('domain_checks')->insert([
                'id' => (string) Str::uuid(),
                'domain_id' => $primaryDomain->id,
                'check_type' => 'uptime',
                'status' => 'ok',
                'started_at' => now()->subMinute(),
                'finished_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('domain_checks')->insert([
                'id' => (string) Str::uuid(),
                'domain_id' => $primaryDomain->id,
                'check_type' => 'http',
                'status' => 'ok',
                'started_at' => now()->subMinute(),
                'finished_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('domain_checks')->insert([
                'id' => (string) Str::uuid(),
                'domain_id' => $primaryDomain->id,
                'check_type' => 'ssl',
                'status' => 'warn',
                'started_at' => now()->subMinute(),
                'finished_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
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
            ->assertJsonPath('web_properties.0.repositories.0.repo_name', 'moveroo-website-astro')
            ->assertJsonPath('web_properties.0.analytics_sources.0.external_id', '6')
            ->assertJsonPath('web_properties.0.analytics_sources.0.install_audit.install_verdict', 'installed_match')
            ->assertJsonPath('web_properties.0.health_summary.checks.uptime', 'ok')
            ->assertJsonPath('web_properties.0.health_summary.checks.http', 'ok')
            ->assertJsonPath('web_properties.0.health_summary.checks.ssl', 'warn')
            ->assertJsonPath('web_properties.0.control_state', 'controlled')
            ->assertJsonPath('web_properties.0.execution_surface', 'astro_repo_controlled')
            ->assertJsonPath('web_properties.0.fleet_managed', true)
            ->assertJsonPath('web_properties.0.controller_repo', 'moveroo-website-astro')
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.has_issue_detail', false)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.issue_detail_snapshot_count', 0)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.latest_issue_detail_captured_at', null)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.has_api_enrichment', false)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.api_snapshot_count', 0)
            ->assertJsonPath('web_properties.0.gsc_evidence_summary.latest_api_captured_at', null)
            ->assertJsonPath('web_properties.0.health_summary.active_alerts_count', 1);
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
            DB::table('domain_checks')->insert([
                'id' => (string) Str::uuid(),
                'domain_id' => $domain->id,
                'check_type' => 'dns',
                'status' => 'fail',
                'started_at' => now()->subMinute(),
                'finished_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
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
