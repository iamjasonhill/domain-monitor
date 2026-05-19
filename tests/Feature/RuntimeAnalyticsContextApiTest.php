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
            'status' => 'active',
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
            ->assertJsonPath('runtime_contexts.0.conversion_surface.rollout_status', 'instrumented')
            ->assertJsonPath('runtime_contexts.0.host_classification.class', 'conversion_host')
            ->assertJsonPath('runtime_contexts.0.host_classification.decision', 'exported')
            ->assertJsonPath('runtime_contexts.0.host_classification.provenance', 'conversion_surface')
            ->assertJsonPath('runtime_contexts.0.host_classification.exports_runtime_context', true)
            ->assertJsonPath('runtime_contexts.0.host_classification.missing_host_warning_policy', 'warn');
    }

    public function test_runtime_analytics_context_feed_exports_non_conversion_operational_hostname_with_classification(): void
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
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $primaryDomain->id,
            'target_moveroo_subdomain_url' => 'https://wemove.moveroo.com.au',
            'target_legacy_bookings_replacement_url' => 'https://removalist.net/booking/create',
            'target_legacy_payments_replacement_url' => 'https://wemove.moveroo.com.au/contact',
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
        ])->getJson('/api/runtime/analytics-contexts?hostname=removalist.net')
            ->assertOk()
            ->assertJsonPath('runtime_contexts.0.hostname', 'removalist.net')
            ->assertJsonPath('runtime_contexts.0.property_slug', 'moveroo-com-au')
            ->assertJsonPath('runtime_contexts.0.site_key', 'moveroo')
            ->assertJsonPath('runtime_contexts.0.ga4.measurement_id', 'G-9F3Y80LEQL')
            ->assertJsonPath('runtime_contexts.0.runtime.path', '/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026')
            ->assertJsonPath('runtime_contexts.0.conversion_surface.rollout_status', null)
            ->assertJsonPath('runtime_contexts.0.host_classification.class', 'login_customer_provider_app_shell_host')
            ->assertJsonPath('runtime_contexts.0.host_classification.decision', 'exported')
            ->assertJsonPath('runtime_contexts.0.host_classification.provenance', 'hostname_link_policy')
            ->assertJsonPath('runtime_contexts.0.host_classification.exports_runtime_context', true)
            ->assertJsonPath('runtime_contexts.0.host_classification.missing_host_warning_policy', 'warn');
    }

    public function test_runtime_analytics_context_feed_exports_configured_runtime_host_override(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('domain_monitor.runtime_analytics.host_overrides', [
            [
                'hostname' => 'quotes.interstateremovalists.net.au',
                'property_slug' => 'interstateremovalists-net-au',
                'class' => 'conversion_host',
                'decision' => 'exported',
                'reason' => 'moveroocombined_runtime_host_override',
                'journey_type' => 'mixed_quote',
            ],
        ]);

        $primaryDomain = Domain::factory()->create([
            'domain' => 'interstateremovalists.net.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'interstateremovalists-net-au',
            'name' => 'Interstate Removalists',
            'site_key' => 'interstateremovalists',
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $primaryDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'ga4',
            'external_id' => 'G-INTERSTATE01',
            'external_name' => 'Interstate Removalists GA4',
            'provider_config' => [
                'site_key' => 'interstateremovalists',
                'property_id' => '123456789',
                'stream_id' => '22334455',
                'measurement_id' => 'G-INTERSTATE01',
                'bigquery_project' => 'mm-interstate-analytics',
            ],
            'is_primary' => true,
            'status' => 'active',
        ]);

        $contract = AnalyticsEventContract::create([
            'key' => 'interstate-full-funnel-v1',
            'name' => 'Interstate Full Funnel',
            'version' => 'v1',
            'contract_type' => 'ga4_web_and_backend',
            'status' => 'active',
        ]);

        WebPropertyEventContract::create([
            'web_property_id' => $property->id,
            'analytics_event_contract_id' => $contract->id,
            'is_primary' => true,
            'rollout_status' => 'instrumented',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/runtime/analytics-contexts?hostname=quotes.interstateremovalists.net.au')
            ->assertOk()
            ->assertJsonPath('runtime_contexts.0.hostname', 'quotes.interstateremovalists.net.au')
            ->assertJsonPath('runtime_contexts.0.property_slug', 'interstateremovalists-net-au')
            ->assertJsonPath('runtime_contexts.0.site_key', 'interstateremovalists')
            ->assertJsonPath('runtime_contexts.0.journey_type', 'mixed_quote')
            ->assertJsonPath('runtime_contexts.0.ga4.measurement_id', 'G-INTERSTATE01')
            ->assertJsonPath('runtime_contexts.0.host_classification.class', 'conversion_host')
            ->assertJsonPath('runtime_contexts.0.host_classification.decision', 'exported')
            ->assertJsonPath('runtime_contexts.0.host_classification.reason', 'moveroocombined_runtime_host_override')
            ->assertJsonPath('runtime_contexts.0.host_classification.provenance', 'runtime_host_override')
            ->assertJsonPath('runtime_contexts.0.host_classification.exports_runtime_context', true)
            ->assertJsonPath('runtime_contexts.0.host_classification.missing_host_warning_policy', 'warn')
            ->assertJsonPath('runtime_contexts.0.runtime.path', '/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026');
    }

    public function test_runtime_analytics_context_feed_classifies_retired_runtime_host_override_as_expected_miss(): void
    {
        config()->set('services.domain_monitor.brain_api_key', 'test-api-key');
        config()->set('domain_monitor.runtime_analytics.host_overrides', [
            [
                'hostname' => 'quotes.interstateremovalists.net.au',
                'property_slug' => 'interstateremovalists-net-au',
                'class' => 'retired',
                'decision' => 'expected_miss',
                'reason' => 'decommissioned_subdomain',
                'warning_policy' => 'suppress',
            ],
        ]);

        $primaryDomain = Domain::factory()->create([
            'domain' => 'interstateremovalists.net.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'interstateremovalists-net-au',
            'name' => 'Interstate Removalists',
            'site_key' => 'interstateremovalists',
            'status' => 'active',
            'primary_domain_id' => $primaryDomain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $primaryDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'ga4',
            'external_id' => 'G-INTERSTATE01',
            'external_name' => 'Interstate Removalists GA4',
            'provider_config' => [
                'site_key' => 'interstateremovalists',
                'property_id' => '123456789',
                'stream_id' => '22334455',
                'measurement_id' => 'G-INTERSTATE01',
                'bigquery_project' => 'mm-interstate-analytics',
            ],
            'is_primary' => true,
            'status' => 'active',
        ]);

        $this->withHeaders([
            'Authorization' => 'Bearer test-api-key',
        ])->getJson('/api/runtime/analytics-contexts?hostname=quotes.interstateremovalists.net.au')
            ->assertOk()
            ->assertJsonPath('runtime_contexts.0.hostname', 'quotes.interstateremovalists.net.au')
            ->assertJsonPath('runtime_contexts.0.property_slug', 'interstateremovalists-net-au')
            ->assertJsonPath('runtime_contexts.0.ga4.measurement_id', null)
            ->assertJsonPath('runtime_contexts.0.runtime.path', null)
            ->assertJsonPath('runtime_contexts.0.event_contract.key', null)
            ->assertJsonPath('runtime_contexts.0.host_classification.class', 'retired')
            ->assertJsonPath('runtime_contexts.0.host_classification.decision', 'expected_miss')
            ->assertJsonPath('runtime_contexts.0.host_classification.reason', 'decommissioned_subdomain')
            ->assertJsonPath('runtime_contexts.0.host_classification.provenance', 'runtime_host_override')
            ->assertJsonPath('runtime_contexts.0.host_classification.exports_runtime_context', false)
            ->assertJsonPath('runtime_contexts.0.host_classification.missing_host_warning_policy', 'suppress');
    }
}
