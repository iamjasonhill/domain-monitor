<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\User;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchConsoleCoverageQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_search_console_coverage_queue(): void
    {
        $user = User::factory()->create();

        $missingDomain = Domain::factory()->create([
            'domain' => 'missing-sc.example.au',
            'is_active' => true,
            'platform' => 'Astro',
        ]);
        $missingProperty = WebProperty::factory()->create([
            'slug' => 'missing-sc',
            'name' => 'Missing SC',
            'status' => 'active',
            'property_type' => 'marketing_site',
            'primary_domain_id' => $missingDomain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $missingProperty->id,
            'domain_id' => $missingDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);
        PropertyAnalyticsSource::create([
            'web_property_id' => $missingProperty->id,
            'provider' => 'matomo',
            'external_id' => '31',
            'external_name' => 'Missing SC',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $prefixDomain = Domain::factory()->create([
            'domain' => 'prefix.example.au',
            'is_active' => true,
            'platform' => 'Astro',
        ]);
        $prefixProperty = WebProperty::factory()->create([
            'slug' => 'prefix-site',
            'name' => 'Prefix Site',
            'status' => 'active',
            'property_type' => 'marketing_site',
            'primary_domain_id' => $prefixDomain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $prefixProperty->id,
            'domain_id' => $prefixDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);
        $prefixSource = PropertyAnalyticsSource::create([
            'web_property_id' => $prefixProperty->id,
            'provider' => 'matomo',
            'external_id' => '32',
            'external_name' => 'Prefix Site',
            'is_primary' => true,
            'status' => 'active',
        ]);
        SearchConsoleCoverageStatus::create([
            'domain_id' => $prefixDomain->id,
            'web_property_id' => $prefixProperty->id,
            'property_analytics_source_id' => $prefixSource->id,
            'source_provider' => 'matomo',
            'matomo_site_id' => '32',
            'matomo_site_name' => 'Prefix Site',
            'mapping_state' => 'url_prefix',
            'property_uri' => 'https://prefix.example.au/',
            'property_type' => 'url-prefix',
            'latest_metric_date' => now()->subDay()->toDateString(),
            'checked_at' => now(),
        ]);

        $readyDomain = Domain::factory()->create([
            'domain' => 'ready.example.au',
            'is_active' => true,
            'platform' => 'Astro',
        ]);
        $readyProperty = WebProperty::factory()->create([
            'slug' => 'ready-site',
            'name' => 'Ready Site',
            'status' => 'active',
            'property_type' => 'marketing_site',
            'primary_domain_id' => $readyDomain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $readyProperty->id,
            'domain_id' => $readyDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);
        $readySource = PropertyAnalyticsSource::create([
            'web_property_id' => $readyProperty->id,
            'provider' => 'matomo',
            'external_id' => '33',
            'external_name' => 'Ready Site',
            'is_primary' => true,
            'status' => 'active',
        ]);
        SearchConsoleCoverageStatus::create([
            'domain_id' => $readyDomain->id,
            'web_property_id' => $readyProperty->id,
            'property_analytics_source_id' => $readySource->id,
            'source_provider' => 'matomo',
            'matomo_site_id' => '33',
            'matomo_site_name' => 'Ready Site',
            'mapping_state' => 'domain_property',
            'property_uri' => 'sc-domain:ready.example.au',
            'property_type' => 'domain',
            'latest_metric_date' => now()->subDay()->toDateString(),
            'checked_at' => now(),
        ]);

        $excludedDomain = Domain::factory()->create([
            'domain' => 'parked.example.au',
            'is_active' => true,
            'platform' => 'Parked',
        ]);
        $excludedProperty = WebProperty::factory()->create([
            'slug' => 'parked-site',
            'name' => 'Parked Site',
            'status' => 'active',
            'property_type' => 'domain_asset',
            'primary_domain_id' => $excludedDomain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $excludedProperty->id,
            'domain_id' => $excludedDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $response = $this->actingAs($user)->get('/search-console-coverage');

        $response->assertOk();
        $response->assertSee('Search Console Coverage');
        $response->assertSee('Missing SC');
        $response->assertSee('Prefix Site');
        $response->assertSee('Ready Site');
        $response->assertSee('Parked Site');
        $response->assertSee('Mapped (URL Prefix)');
        $response->assertSee('Mapped (Domain Property)');
    }
}
