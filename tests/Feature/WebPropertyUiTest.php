<?php

namespace Tests\Feature;

use App\Models\AnalyticsInstallAudit;
use App\Models\Domain;
use App\Models\DomainSeoBaseline;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\User;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebPropertyUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_web_properties_index(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'moveroo.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'moveroo-website',
            'name' => 'Moveroo Website',
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

        $response = $this->actingAs($user)->get('/web-properties');

        $response->assertOk();
        $response->assertSee('Web Properties');
        $response->assertSee('Moveroo Website');
        $response->assertSee('moveroo.com.au');
    }

    public function test_authenticated_user_can_view_web_property_detail(): void
    {
        $user = User::factory()->create();
        $primaryDomain = Domain::factory()->create([
            'domain' => 'movingagain.com.au',
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
        ]);
        $aliasDomain = Domain::factory()->create([
            'domain' => 'movingagain.net.au',
            'is_active' => true,
            'dns_config_name' => 'Parked',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'moving-again',
            'name' => 'Moving Again',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => $primaryDomain->id,
            'production_url' => 'https://movingagain.com.au',
            'notes' => 'Review alias grouping before merge.',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $aliasDomain->id,
            'usage_type' => 'alias',
            'is_canonical' => false,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'moving-again-astro',
            'repo_provider' => 'local_only',
            'local_path' => '/Users/jasonhill/Projects/websites/moving-again-astro',
            'framework' => 'Astro',
            'is_primary' => true,
        ]);

        PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '7',
            'external_name' => 'Car transport by Moving Again',
            'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo ',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $source = PropertyAnalyticsSource::query()->where('web_property_id', $property->id)->firstOrFail();

        AnalyticsInstallAudit::create([
            'property_analytics_source_id' => $source->id,
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '7',
            'external_name' => 'Car transport by Moving Again',
            'expected_tracker_host' => 'stats.redirection.com.au',
            'install_verdict' => 'not_detected',
            'best_url' => 'https://movingagain.com.au/',
            'detected_site_ids' => [],
            'detected_tracker_hosts' => [],
            'summary' => 'No Matomo snippet detected.',
            'checked_at' => now(),
            'raw_payload' => ['verdict' => 'not_detected'],
        ]);

        $response = $this->actingAs($user)->get('/web-properties/moving-again');

        $response->assertOk();
        $response->assertSee('Moving Again');
        $response->assertSee('movingagain.com.au');
        $response->assertSee('movingagain.net.au');
        $response->assertSee('moving-again-astro');
        $response->assertSee('Car transport by Moving Again');
        $response->assertSee('not detected');
        $response->assertSee('No Matomo snippet detected.');
        $response->assertSee('Automation Checklist');
        $response->assertSee('Needs Matomo');
    }

    public function test_web_property_detail_shows_manual_csv_pending_checklist_state(): void
    {
        $user = User::factory()->create();
        $domain = Domain::factory()->create([
            'domain' => 'checklist.example.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'checklist-site',
            'name' => 'Checklist Site',
            'property_type' => 'marketing_site',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://checklist.example.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'checklist-site-repo',
            'repo_provider' => 'local',
            'local_path' => '/tmp/checklist-site',
            'framework' => 'Astro',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $source = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '88',
            'external_name' => 'Checklist Site',
            'workspace_path' => '/tmp/matomo',
            'is_primary' => true,
            'status' => 'active',
        ]);

        AnalyticsInstallAudit::create([
            'property_analytics_source_id' => $source->id,
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '88',
            'external_name' => 'Checklist Site',
            'expected_tracker_host' => 'stats.example.au',
            'install_verdict' => 'installed_match',
            'best_url' => 'https://checklist.example.au/',
            'detected_site_ids' => ['88'],
            'detected_tracker_hosts' => ['stats.example.au'],
            'summary' => 'Tracker matches the linked Matomo site.',
            'checked_at' => now(),
            'raw_payload' => ['verdict' => 'installed_match'],
        ]);

        SearchConsoleCoverageStatus::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'source_provider' => 'matomo',
            'matomo_site_id' => '88',
            'matomo_site_name' => 'Checklist Site',
            'mapping_state' => 'domain_property',
            'property_uri' => 'sc-domain:checklist.example.au',
            'property_type' => 'domain',
            'latest_metric_date' => now()->subDay()->toDateString(),
            'checked_at' => now(),
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'source_provider' => 'search_console',
            'matomo_site_id' => '88',
            'search_console_property_uri' => 'sc-domain:checklist.example.au',
            'search_type' => 'web',
            'import_method' => 'matomo_api',
            'clicks' => 20,
            'impressions' => 120,
            'ctr' => 0.16,
            'average_position' => 9.8,
        ]);

        $response = $this->actingAs($user)->get('/web-properties/checklist-site');

        $response->assertOk();
        $response->assertSee('Automation Checklist');
        $response->assertSee('Checklist Site');
        $response->assertSee('Manual CSV pending');
        $response->assertSee('Open related queue');
    }
}
