<?php

namespace Tests\Feature;

use App\Models\AnalyticsInstallAudit;
use App\Models\AnalyticsSourceObservation;
use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\User;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatomoCoverageQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_matomo_coverage_queue(): void
    {
        $user = User::factory()->create();

        $coveredDomain = Domain::factory()->create([
            'domain' => 'covered.example.au',
            'is_active' => true,
            'platform' => 'Astro',
        ]);
        $coveredProperty = WebProperty::factory()->create([
            'slug' => 'covered-example',
            'name' => 'Covered Example',
            'status' => 'active',
            'property_type' => 'marketing_site',
            'primary_domain_id' => $coveredDomain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $coveredProperty->id,
            'domain_id' => $coveredDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);
        $coveredSource = PropertyAnalyticsSource::create([
            'web_property_id' => $coveredProperty->id,
            'provider' => 'matomo',
            'external_id' => '8',
            'external_name' => 'Covered Example',
            'is_primary' => true,
            'status' => 'active',
        ]);
        AnalyticsInstallAudit::create([
            'property_analytics_source_id' => $coveredSource->id,
            'web_property_id' => $coveredProperty->id,
            'provider' => 'matomo',
            'external_id' => '8',
            'external_name' => 'Covered Example',
            'install_verdict' => 'installed_match',
            'best_url' => 'https://covered.example.au/',
            'detected_site_ids' => ['8'],
            'detected_tracker_hosts' => ['stats.redirection.com.au'],
            'summary' => 'Matomo snippet detected with the expected tracker host and site ID.',
            'checked_at' => now(),
            'raw_payload' => ['id_site' => '8'],
        ]);

        $needsBindingDomain = Domain::factory()->create([
            'domain' => 'needsbinding.example.au',
            'is_active' => true,
            'platform' => 'Astro',
        ]);
        $needsBindingProperty = WebProperty::factory()->create([
            'slug' => 'needs-binding',
            'name' => 'Needs Binding',
            'status' => 'active',
            'property_type' => 'marketing_site',
            'primary_domain_id' => $needsBindingDomain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $needsBindingProperty->id,
            'domain_id' => $needsBindingDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $attentionDomain = Domain::factory()->create([
            'domain' => 'attention.example.au',
            'is_active' => true,
            'platform' => 'Astro',
        ]);
        $attentionProperty = WebProperty::factory()->create([
            'slug' => 'needs-attention',
            'name' => 'Needs Attention',
            'status' => 'active',
            'property_type' => 'marketing_site',
            'primary_domain_id' => $attentionDomain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $attentionProperty->id,
            'domain_id' => $attentionDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);
        $attentionSource = PropertyAnalyticsSource::create([
            'web_property_id' => $attentionProperty->id,
            'provider' => 'matomo',
            'external_id' => '6',
            'external_name' => 'Needs Attention',
            'is_primary' => true,
            'status' => 'active',
        ]);
        AnalyticsInstallAudit::create([
            'property_analytics_source_id' => $attentionSource->id,
            'web_property_id' => $attentionProperty->id,
            'provider' => 'matomo',
            'external_id' => '6',
            'external_name' => 'Needs Attention',
            'install_verdict' => 'not_detected',
            'best_url' => 'https://attention.example.au/',
            'detected_site_ids' => [],
            'detected_tracker_hosts' => [],
            'summary' => 'No Matomo snippet detected.',
            'checked_at' => now(),
            'raw_payload' => ['id_site' => '6'],
        ]);

        $excludedDomain = Domain::factory()->create([
            'domain' => 'parked.example.au',
            'is_active' => true,
            'platform' => 'Parked',
        ]);
        $excludedProperty = WebProperty::factory()->create([
            'slug' => 'parked-example',
            'name' => 'Parked Example',
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

        AnalyticsSourceObservation::create([
            'provider' => 'matomo',
            'external_id' => '9',
            'external_name' => 'Needs Mapping',
            'install_verdict' => 'installed_match',
            'best_url' => 'https://needsbinding.example.au/',
            'detected_site_ids' => ['9'],
            'detected_tracker_hosts' => ['stats.redirection.com.au'],
            'summary' => 'Matomo snippet detected with the expected tracker host and site ID.',
            'checked_at' => now(),
            'raw_payload' => [
                'urls' => ['https://needsbinding.example.au'],
            ],
        ]);

        $response = $this->actingAs($user)->get('/matomo-coverage');

        $response->assertOk();
        $response->assertSee('Matomo Coverage');
        $response->assertSee('Needs Binding');
        $response->assertSee('Needs Attention');
        $response->assertSee('Covered Example');
        $response->assertSee('Needs Binding');
        $response->assertSee('No Matomo snippet detected.');
        $response->assertSee('Needs Mapping');
        $response->assertSee('Suggested: Needs Binding');
        $response->assertSee('Parked Example');
    }
}
