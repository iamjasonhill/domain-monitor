<?php

namespace Tests\Feature;

use App\Models\AnalyticsInstallAudit;
use App\Models\Domain;
use App\Models\DomainAlert;
use App\Models\DomainCheck;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
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
            'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo ',
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
            ->assertJsonPath('web_properties.0.health_summary.checks.http', 'ok')
            ->assertJsonPath('web_properties.0.health_summary.checks.ssl', 'warn')
            ->assertJsonPath('web_properties.0.health_summary.active_alerts_count', 1);
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
