<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncConversionSurfacesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_backfills_quote_conversion_surfaces_from_property_targets(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'movingagain.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'movingagain-com-au',
            'name' => 'movingagain.com.au',
            'primary_domain_id' => $domain->id,
            'platform' => 'Astro',
            'target_moveroo_subdomain_url' => 'https://quotes.movingagain.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'ga4',
            'external_id' => 'G-K6VBFJGYYK',
            'external_name' => 'movingagain.com.au',
            'provider_config' => [
                'property_id' => '533626872',
                'stream_id' => '14399248676',
                'measurement_id' => 'G-K6VBFJGYYK',
            ],
            'is_primary' => true,
            'status' => 'active',
        ]);

        $this->assertSame(0, Artisan::call('conversion-surfaces:sync-target-subdomains'));

        $quoteDomain = Domain::query()->where('domain', 'quotes.movingagain.com.au')->first();

        $this->assertNotNull($quoteDomain);
        $this->assertSame('Laravel', $quoteDomain->platform);

        $this->assertDatabaseHas('web_property_domains', [
            'web_property_id' => $property->id,
            'domain_id' => $quoteDomain->id,
            'usage_type' => 'subdomain',
        ]);

        $this->assertDatabaseHas('web_property_conversion_surfaces', [
            'web_property_id' => $property->id,
            'domain_id' => $quoteDomain->id,
            'hostname' => 'quotes.movingagain.com.au',
            'surface_type' => 'quote_subdomain',
            'runtime_driver' => 'Laravel',
            'runtime_path' => '/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026',
            'analytics_binding_mode' => 'inherits_property',
            'event_contract_binding_mode' => 'inherits_property',
            'tenant_key' => 'movingagain-com-au',
        ]);
    }

    public function test_it_applies_property_level_conversion_surface_overrides_when_resyncing_existing_surfaces(): void
    {
        config()->set('domain_monitor.conversion_surfaces.overrides.properties.vehicle-net-au', [
            'journey_type' => 'vehicle_quote',
            'runtime_label' => 'Moveroo Cars 2026',
            'runtime_path' => '/Users/jasonhill/Projects/laravel-projects/Moveroo-Cars-2026',
            'notes' => 'Legacy vehicle quoting surface attached to Moveroo Cars 2026. Phase-out is in progress, so keep visibility high but do not expand or normalize this surface onto the maintained removals runtime.',
            'replace_notes' => true,
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'vehicle.net.au',
            'is_active' => true,
        ]);

        $quoteDomain = Domain::factory()->create([
            'domain' => 'quoting.vehicle.net.au',
            'platform' => 'Laravel',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'vehicle-net-au',
            'name' => 'vehicle.net.au',
            'primary_domain_id' => $domain->id,
            'platform' => 'Laravel',
            'target_moveroo_subdomain_url' => 'https://quoting.vehicle.net.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $quoteDomain->id,
            'usage_type' => 'subdomain',
            'is_canonical' => false,
        ]);

        \App\Models\WebPropertyConversionSurface::create([
            'web_property_id' => $property->id,
            'domain_id' => $quoteDomain->id,
            'hostname' => 'quoting.vehicle.net.au',
            'surface_type' => 'quote_subdomain',
            'journey_type' => 'mixed_quote',
            'runtime_driver' => 'Laravel',
            'runtime_label' => 'Moveroo Removals 2026',
            'runtime_path' => '/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026',
            'analytics_binding_mode' => 'inherits_property',
            'event_contract_binding_mode' => 'inherits_property',
            'rollout_status' => 'defined',
            'notes' => 'Backfilled from the property quote-subdomain target.',
        ]);

        $this->assertSame(0, Artisan::call('conversion-surfaces:sync-target-subdomains', [
            'propertySlug' => 'vehicle-net-au',
        ]));

        $this->assertDatabaseHas('web_property_conversion_surfaces', [
            'web_property_id' => $property->id,
            'domain_id' => $quoteDomain->id,
            'hostname' => 'quoting.vehicle.net.au',
            'journey_type' => 'vehicle_quote',
            'runtime_label' => 'Moveroo Cars 2026',
            'runtime_path' => '/Users/jasonhill/Projects/laravel-projects/Moveroo-Cars-2026',
            'notes' => 'Legacy vehicle quoting surface attached to Moveroo Cars 2026. Phase-out is in progress, so keep visibility high but do not expand or normalize this surface onto the maintained removals runtime.',
        ]);
    }
}
