<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BootstrapWebPropertiesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_bootstraps_properties_from_domains_with_overrides_and_repo_matching(): void
    {
        $websitesRoot = storage_path('framework/testing/websites');
        File::deleteDirectory($websitesRoot);
        File::ensureDirectoryExists($websitesRoot.'/moveroo-website-astro');
        File::put(
            $websitesRoot.'/moveroo-website-astro/package.json',
            json_encode([
                'dependencies' => [
                    'astro' => '^5.0.0',
                ],
            ], JSON_PRETTY_PRINT)
        );

        config()->set('domain_monitor.web_property_bootstrap', [
            'websites_root' => $websitesRoot,
            'overrides' => [
                'moveroo.com.au' => [
                    'slug' => 'moveroo-website',
                    'name' => 'Moveroo Website',
                    'property_type' => 'marketing_site',
                    'repository' => [
                        'repo_name' => 'moveroo-website-astro',
                        'local_path' => $websitesRoot.'/moveroo-website-astro',
                        'framework' => 'Astro',
                    ],
                    'analytics_sources' => [
                        [
                            'provider' => 'matomo',
                            'external_id' => '6',
                            'external_name' => 'Moveroo website',
                            'workspace_path' => '/Users/jasonhill/Projects/2026 Projects/Matamo',
                        ],
                    ],
                ],
            ],
        ]);

        $moveroo = Domain::factory()->create([
            'domain' => 'moveroo.com.au',
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
        ]);

        $parked = Domain::factory()->create([
            'domain' => 'parked-example.com.au',
            'is_active' => true,
            'dns_config_name' => 'Parked',
        ]);

        $this->artisan('web-properties:bootstrap')
            ->assertSuccessful();

        $this->assertDatabaseCount('web_properties', 2);
        $this->assertDatabaseCount('web_property_domains', 2);

        $moverooProperty = WebProperty::query()->where('slug', 'moveroo-website')->firstOrFail();
        $this->assertSame('marketing_site', $moverooProperty->property_type);
        $this->assertSame($moveroo->id, $moverooProperty->primary_domain_id);

        $parkedProperty = WebProperty::query()->where('slug', 'parked-example-com-au')->firstOrFail();
        $this->assertSame('domain_asset', $parkedProperty->property_type);

        $this->assertDatabaseHas('web_property_domains', [
            'web_property_id' => $moverooProperty->id,
            'domain_id' => $moveroo->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $repository = PropertyRepository::query()->where('web_property_id', $moverooProperty->id)->firstOrFail();
        $this->assertSame('moveroo-website-astro', $repository->repo_name);
        $this->assertSame('Astro', $repository->framework);

        $analytics = PropertyAnalyticsSource::query()->where('web_property_id', $moverooProperty->id)->firstOrFail();
        $this->assertSame('matomo', $analytics->provider);
        $this->assertSame('6', $analytics->external_id);

        $moverooLink = WebPropertyDomain::query()->where('web_property_id', $moverooProperty->id)->firstOrFail();
        $this->assertSame($moveroo->id, $moverooLink->domain_id);
    }
}
