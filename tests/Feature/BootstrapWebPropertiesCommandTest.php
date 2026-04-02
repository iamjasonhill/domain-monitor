<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
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
        $packageJson = json_encode([
            'dependencies' => [
                'astro' => '^5.0.0',
            ],
        ], JSON_PRETTY_PRINT);
        $this->assertIsString($packageJson);
        File::put(
            $websitesRoot.'/moveroo-website-astro/package.json',
            $packageJson
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

        $this->assertSame(0, Artisan::call('web-properties:bootstrap'));

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

    public function test_it_refreshes_existing_repository_metadata_from_bootstrap_overrides(): void
    {
        config()->set('domain_monitor.web_property_bootstrap', [
            'websites_root' => storage_path('framework/testing/websites'),
            'overrides' => [
                'cartransport.movingagain.com.au' => [
                    'slug' => 'ma-car-transport',
                    'name' => 'Moving Again Car Transport',
                    'property_type' => 'website',
                    'repository' => [
                        'repo_name' => 'moveroo/ma-catrans-program',
                        'repo_provider' => 'github',
                        'repo_url' => 'https://github.com/moveroo/ma-catrans-program',
                        'local_path' => '/Users/jasonhill/Projects/websites/ma-car-transport-astro',
                        'framework' => 'Astro',
                        'is_controller' => true,
                        'deployment_provider' => 'vercel',
                    ],
                ],
            ],
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'cartransport.movingagain.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'ma-car-transport',
            'name' => 'Moving Again Car Transport',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'moveroo/ma-catrans-program',
            'repo_provider' => 'github',
            'repo_url' => 'https://github.com/iamjasonhill/cartransport-astro',
            'local_path' => '/Users/jasonhill/Projects/websites/cartransport-new-astro',
            'framework' => 'Astro',
            'is_primary' => true,
            'is_controller' => false,
        ]);

        $this->assertSame(0, Artisan::call('web-properties:bootstrap', ['--refresh-links' => true]));

        $repository = PropertyRepository::query()
            ->where('web_property_id', $property->id)
            ->where('repo_name', 'moveroo/ma-catrans-program')
            ->firstOrFail();

        $this->assertSame('https://github.com/moveroo/ma-catrans-program', $repository->repo_url);
        $this->assertSame('/Users/jasonhill/Projects/websites/ma-car-transport-astro', $repository->local_path);
        $this->assertTrue($repository->is_controller);
        $this->assertSame('vercel', $repository->deployment_provider);
    }

    public function test_refresh_links_does_not_count_non_fillable_repository_keys_as_changes(): void
    {
        $websitesRoot = storage_path('framework/testing/websites');
        File::deleteDirectory($websitesRoot);
        File::ensureDirectoryExists($websitesRoot.'/example-astro');
        $packageJson = json_encode([
            'dependencies' => [
                'astro' => '^5.0.0',
            ],
        ], JSON_PRETTY_PRINT);
        $this->assertIsString($packageJson);
        File::put($websitesRoot.'/example-astro/package.json', $packageJson);

        config()->set('domain_monitor.web_property_bootstrap', [
            'websites_root' => $websitesRoot,
            'overrides' => [],
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'example-astro.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'example-astro-com-au',
            'name' => 'Example Astro',
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

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => 'example-astro',
            'repo_provider' => 'local_only',
            'local_path' => $websitesRoot.'/example-astro',
            'framework' => 'Astro',
            'is_primary' => true,
            'is_controller' => false,
        ]);

        $command = $this->app->make(\App\Console\Commands\BootstrapWebProperties::class);
        $method = new \ReflectionMethod($command, 'syncRepositoryLink');
        $method->setAccessible(true);

        $result = $method->invoke($command, $property, [
            'repo_name' => 'example-astro',
            'repo_provider' => 'local_only',
            'local_path' => $websitesRoot.'/example-astro',
            'framework' => 'Astro',
            'is_primary' => true,
            'is_controller' => false,
            'match_key' => 'example-astro',
        ], false);

        $this->assertFalse($result);
    }
}
