<?php

namespace Tests\Feature;

use App\Models\AnalyticsEventContract;
use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Models\WebPropertyConversionSurface;
use App\Models\WebPropertyDomain;
use App\Models\WebPropertyEventContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RuntimeAnalyticsContextApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_analytics_context_feed_returns_lightweight_hostname_resolution_data(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');

        $primaryDomain = Domain::factory()->create([
            'domain' => 'moveroo.com.au',
            'is_active' => true,
        ]);

        $surfaceDomain = Domain::factory()->create([
            'domain' => 'quotes.moveroo.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'moveroo-com-au',
            'name' => 'Moveroo Website',
            'site_key' => 'moveroo',
            'primary_domain_id' => $primaryDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $ga4 = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'ga4',
            'external_id' => 'G-9F3Y80LEQL',
            'external_name' => 'Moveroo GA4',
            'provider_config' => [
                'site_key' => 'moveroo',
                'property_id' => '457902172',
                'stream_id' => '9677257871',
                'measurement_id' => 'G-9F3Y80LEQL',
                'bigquery_project' => 'mm-moveroo-analytics',
            ],
            'is_primary' => true,
            'status' => 'active',
        ]);

        $contract = AnalyticsEventContract::create([
            'key' => 'moveroo-full-funnel-v1',
            'name' => 'Moveroo Full Funnel',
            'version' => 'v1',
            'contract_type' => 'ga4_web_and_backend',
            'status' => 'active',
        ]);

        $assignment = WebPropertyEventContract::create([
            'web_property_id' => $property->id,
            'analytics_event_contract_id' => $contract->id,
            'is_primary' => true,
            'rollout_status' => 'instrumented',
        ]);

        WebPropertyConversionSurface::create([
            'web_property_id' => $property->id,
            'domain_id' => $surfaceDomain->id,
            'hostname' => 'quotes.moveroo.com.au',
            'surface_type' => 'quote_subdomain',
            'journey_type' => 'mixed_quote',
            'runtime_driver' => 'Laravel',
            'runtime_label' => 'Moveroo Removals 2026',
            'runtime_path' => '/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026',
            'analytics_binding_mode' => 'inherits_property',
            'event_contract_binding_mode' => 'inherits_property',
            'rollout_status' => 'instrumented',
            'property_analytics_source_id' => $ga4->id,
            'web_property_event_contract_id' => $assignment->id,
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/runtime/analytics-contexts?hostname=quotes.moveroo.com.au')
            ->assertOk()
            ->assertJsonPath('source_system', 'domain-monitor-runtime-analytics')
            ->assertJsonPath('contract_version', 1)
            ->assertJsonPath('runtime_contexts.0.hostname', 'quotes.moveroo.com.au')
            ->assertJsonPath('runtime_contexts.0.property_slug', 'moveroo-com-au')
            ->assertJsonPath('runtime_contexts.0.site_key', 'moveroo')
            ->assertJsonPath('runtime_contexts.0.journey_type', 'mixed_quote')
            ->assertJsonPath('runtime_contexts.0.runtime.path', '/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026')
            ->assertJsonPath('runtime_contexts.0.ga4.measurement_id', 'G-9F3Y80LEQL')
            ->assertJsonPath('runtime_contexts.0.event_contract.key', 'moveroo-full-funnel-v1')
            ->assertJsonPath('runtime_contexts.0.event_contract.rollout_status', 'instrumented')
            ->assertJsonPath('runtime_contexts.0.conversion_surface.rollout_status', 'instrumented');
    }
}
