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
        $this->assertSame('Laravel', $quoteDomain?->platform);

        $this->assertDatabaseHas('web_property_domains', [
            'web_property_id' => $property->id,
            'domain_id' => $quoteDomain?->id,
            'usage_type' => 'subdomain',
        ]);

        $this->assertDatabaseHas('web_property_conversion_surfaces', [
            'web_property_id' => $property->id,
            'domain_id' => $quoteDomain?->id,
            'hostname' => 'quotes.movingagain.com.au',
            'surface_type' => 'quote_subdomain',
            'runtime_driver' => 'Laravel',
            'runtime_path' => '/Users/jasonhill/Projects/laravel-projects/Moveroo Removals 2026',
            'analytics_binding_mode' => 'inherits_property',
            'event_contract_binding_mode' => 'inherits_property',
            'tenant_key' => 'movingagain-com-au',
        ]);
    }
}
