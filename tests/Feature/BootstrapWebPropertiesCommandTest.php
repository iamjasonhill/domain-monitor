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
                    'slug' => 'moveroo-com-au',
                    'name' => 'Moveroo Website',
                    'property_type' => 'marketing_site',
                    'repository' => [
                        'repo_name' => 'MM-moveroo.com.au',
                        'local_path' => $websitesRoot.'/moveroo-website-astro',
                        'framework' => 'Astro',
                    ],
                    'analytics_sources' => [
                        [
                            'provider' => 'ga4',
                            'external_id' => 'G-MOVEROO123',
                            'external_name' => 'Moveroo GA4',
                            'workspace_path' => '/Users/jasonhill/Projects/Business/operations/MM-Google',
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

        $moverooProperty = WebProperty::query()->where('slug', 'moveroo-com-au')->firstOrFail();
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
        $this->assertSame('MM-moveroo.com.au', $repository->repo_name);
        $this->assertSame('Astro', $repository->framework);

        $analytics = PropertyAnalyticsSource::query()->where('web_property_id', $moverooProperty->id)->firstOrFail();
        $this->assertSame('ga4', $analytics->provider);
        $this->assertSame('G-MOVEROO123', $analytics->external_id);

        $moverooLink = WebPropertyDomain::query()->where('web_property_id', $moverooProperty->id)->firstOrFail();
        $this->assertSame($moveroo->id, $moverooLink->domain_id);
    }

    public function test_it_discovers_repo_url_from_package_json_for_auto_matched_repo(): void
    {
        $websitesRoot = storage_path('framework/testing/websites');
        File::deleteDirectory($websitesRoot);
        File::ensureDirectoryExists($websitesRoot.'/example-astro');
        $packageJson = json_encode([
            'dependencies' => [
                'astro' => '^5.0.0',
            ],
            'repository' => 'git+https://github.com/moveroo/example-astro.git',
        ], JSON_PRETTY_PRINT);
        $this->assertIsString($packageJson);
        File::put($websitesRoot.'/example-astro/package.json', $packageJson);

        config()->set('domain_monitor.web_property_bootstrap', [
            'websites_root' => $websitesRoot,
            'overrides' => [],
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'example.com.au',
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
        ]);

        $this->assertSame(0, Artisan::call('web-properties:bootstrap'));

        $property = WebProperty::query()->where('primary_domain_id', $domain->id)->firstOrFail();
        $repository = PropertyRepository::query()->where('web_property_id', $property->id)->firstOrFail();

        $this->assertSame('https://github.com/moveroo/example-astro', $repository->repo_url);
        $this->assertSame('github', $repository->repo_provider);
    }

    public function test_it_discovers_repo_url_from_git_origin_when_package_json_has_no_repository(): void
    {
        $websitesRoot = storage_path('framework/testing/websites');
        File::deleteDirectory($websitesRoot);
        File::ensureDirectoryExists($websitesRoot.'/origin-only-astro/.git');
        $packageJson = json_encode([
            'dependencies' => [
                'astro' => '^5.0.0',
            ],
        ], JSON_PRETTY_PRINT);
        $this->assertIsString($packageJson);
        File::put($websitesRoot.'/origin-only-astro/package.json', $packageJson);
        File::put(
            $websitesRoot.'/origin-only-astro/.git/config',
            "[remote \"origin\"]\n\turl = git@github.com:moveroo/origin-only-astro.git\n"
        );

        config()->set('domain_monitor.web_property_bootstrap', [
            'websites_root' => $websitesRoot,
            'overrides' => [],
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'origin-only.com.au',
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
        ]);

        $this->assertSame(0, Artisan::call('web-properties:bootstrap'));

        $property = WebProperty::query()->where('primary_domain_id', $domain->id)->firstOrFail();
        $repository = PropertyRepository::query()->where('web_property_id', $property->id)->firstOrFail();

        $this->assertSame('https://github.com/moveroo/origin-only-astro', $repository->repo_url);
        $this->assertSame('github', $repository->repo_provider);
    }

    public function test_it_discovers_private_repository_hosts_when_package_json_declares_them(): void
    {
        $websitesRoot = storage_path('framework/testing/websites');
        File::deleteDirectory($websitesRoot);
        File::ensureDirectoryExists($websitesRoot.'/private-origin-astro');
        $packageJson = json_encode([
            'dependencies' => [
                'astro' => '^5.0.0',
            ],
            'repository' => 'https://git.internal.example.com/moveroo/private-origin-astro.git',
        ], JSON_PRETTY_PRINT);
        $this->assertIsString($packageJson);
        File::put($websitesRoot.'/private-origin-astro/package.json', $packageJson);

        config()->set('domain_monitor.web_property_bootstrap', [
            'websites_root' => $websitesRoot,
            'overrides' => [],
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'private-origin.com.au',
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
        ]);

        $this->assertSame(0, Artisan::call('web-properties:bootstrap'));

        $property = WebProperty::query()->where('primary_domain_id', $domain->id)->firstOrFail();
        $repository = PropertyRepository::query()->where('web_property_id', $property->id)->firstOrFail();

        $this->assertSame('https://git.internal.example.com/moveroo/private-origin-astro', $repository->repo_url);
        $this->assertSame('git', $repository->repo_provider);
    }

    public function test_it_discovers_repo_url_from_realistic_git_origin_config(): void
    {
        $websitesRoot = storage_path('framework/testing/websites');
        File::deleteDirectory($websitesRoot);
        File::ensureDirectoryExists($websitesRoot.'/realistic-origin-astro/.git');
        $packageJson = json_encode([
            'dependencies' => [
                'astro' => '^5.0.0',
            ],
        ], JSON_PRETTY_PRINT);
        $this->assertIsString($packageJson);
        File::put($websitesRoot.'/realistic-origin-astro/package.json', $packageJson);
        File::put(
            $websitesRoot.'/realistic-origin-astro/.git/config',
            implode("\n", [
                '[core]',
                "\trepositoryformatversion = 0",
                '[remote "origin"]',
                "\turl = git@github.com:moveroo/realistic-origin-astro.git",
                "\tfetch = +refs/heads/*:refs/remotes/origin/*",
                '[branch "main"]',
                "\tremote = origin",
                "\tmerge = refs/heads/main",
                '',
            ])
        );

        config()->set('domain_monitor.web_property_bootstrap', [
            'websites_root' => $websitesRoot,
            'overrides' => [],
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'realistic-origin.com.au',
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
        ]);

        $this->assertSame(0, Artisan::call('web-properties:bootstrap'));

        $property = WebProperty::query()->where('primary_domain_id', $domain->id)->firstOrFail();
        $repository = PropertyRepository::query()->where('web_property_id', $property->id)->firstOrFail();

        $this->assertSame('https://github.com/moveroo/realistic-origin-astro', $repository->repo_url);
        $this->assertSame('github', $repository->repo_provider);
    }

    public function test_it_discovers_private_repository_hosts_from_git_origin(): void
    {
        $websitesRoot = storage_path('framework/testing/websites');
        File::deleteDirectory($websitesRoot);
        File::ensureDirectoryExists($websitesRoot.'/private-git-origin-astro/.git');
        $packageJson = json_encode([
            'dependencies' => [
                'astro' => '^5.0.0',
            ],
        ], JSON_PRETTY_PRINT);
        $this->assertIsString($packageJson);
        File::put($websitesRoot.'/private-git-origin-astro/package.json', $packageJson);
        File::put(
            $websitesRoot.'/private-git-origin-astro/.git/config',
            implode("\n", [
                '[remote "origin"]',
                "\turl = git@git.internal.example.com:moveroo/private-git-origin-astro.git",
                "\tfetch = +refs/heads/*:refs/remotes/origin/*",
                '',
            ])
        );

        config()->set('domain_monitor.web_property_bootstrap', [
            'websites_root' => $websitesRoot,
            'overrides' => [],
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'private-git-origin.com.au',
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
        ]);

        $this->assertSame(0, Artisan::call('web-properties:bootstrap'));

        $property = WebProperty::query()->where('primary_domain_id', $domain->id)->firstOrFail();
        $repository = PropertyRepository::query()->where('web_property_id', $property->id)->firstOrFail();

        $this->assertSame('https://git.internal.example.com/moveroo/private-git-origin-astro', $repository->repo_url);
        $this->assertSame('git', $repository->repo_provider);
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
                        'local_path' => '/Users/jasonhill/Projects/Business/websites/ma-car-transport-astro',
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
            'local_path' => '/Users/jasonhill/Projects/Business/websites/cartransport-new-astro',
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
        $this->assertSame('/Users/jasonhill/Projects/Business/websites/ma-car-transport-astro', $repository->local_path);
        $this->assertTrue($repository->is_controller);
        $this->assertSame('vercel', $repository->deployment_provider);
    }

    public function test_it_refreshes_existing_target_contact_url_from_bootstrap_overrides(): void
    {
        config()->set('domain_monitor.web_property_bootstrap', [
            'websites_root' => storage_path('framework/testing/websites'),
            'overrides' => [
                'perthinterstateremovalists.com.au' => [
                    'slug' => 'perthinterstateremovalists-com-au',
                    'name' => 'perthinterstateremovalists.com.au',
                    'property_type' => 'website',
                    'target_contact_us_page_url' => 'https://quoting.perthinterstateremovalists.com.au/contact',
                ],
            ],
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'perthinterstateremovalists.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'perthinterstateremovalists-com-au',
            'name' => 'perthinterstateremovalists.com.au',
            'property_type' => 'website',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
            'target_contact_us_page_url' => 'https://quoting.perthinterstateremovalists.com.aucontact',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $this->assertSame(0, Artisan::call('web-properties:bootstrap', ['--refresh-links' => true]));

        $this->assertSame(
            'https://quoting.perthinterstateremovalists.com.au/contact',
            $property->fresh()->target_contact_us_page_url
        );
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
