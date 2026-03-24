<?php

namespace Tests\Feature;

use App\Models\AnalyticsInstallAudit;
use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
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
    }
}
