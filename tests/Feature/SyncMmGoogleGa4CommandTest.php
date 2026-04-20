<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SyncMmGoogleGa4CommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_ga4_bindings_from_mm_google_without_replacing_existing_primary_source(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'movingagain.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'movingagain-com-au',
            'name' => 'movingagain.com.au',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://movingagain.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '11',
            'external_name' => 'movingagain matomo',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $configPath = storage_path('framework/testing/mm-google-sites.json');
        File::ensureDirectoryExists(dirname($configPath));
        $configJson = json_encode([
            'defaults' => [
                'currencyCode' => 'AUD',
                'timeZone' => 'Australia/Brisbane',
                'bigQueryDatasetLocation' => 'australia-southeast1',
                'provisioning' => [
                    'dailyExportEnabled' => true,
                ],
                'keyEvents' => ['generate_lead'],
            ],
            'sites' => [
                [
                    'key' => 'movingagain',
                    'displayName' => 'movingagain.com.au',
                    'websiteUrl' => 'https://movingagain.com.au',
                    'analyticsAccount' => 'accounts/328441504',
                    'bigQueryProject' => 'mm-brain-2026',
                    'propertyId' => '533626872',
                    'streamId' => '14399248676',
                    'measurementId' => 'G-K6VBFJGYYK',
                    'measurementProtocolSecretName' => null,
                    'tags' => ['production', 'brand:movingagain'],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->assertIsString($configJson);
        File::put($configPath, $configJson);

        $this->assertSame(0, Artisan::call('analytics:sync-mm-google-ga4', [
            '--config-path' => $configPath,
            '--workspace-path' => '/Users/jasonhill/Projects/2026 Projects/MM-Google',
        ]));

        $ga4 = PropertyAnalyticsSource::query()
            ->where('web_property_id', $property->id)
            ->where('provider', 'ga4')
            ->firstOrFail();

        $this->assertSame('G-K6VBFJGYYK', $ga4->external_id);
        $this->assertSame('movingagain.com.au', $ga4->external_name);
        $this->assertFalse($ga4->is_primary);
        $this->assertSame('active', $ga4->status);
        $this->assertSame('/Users/jasonhill/Projects/2026 Projects/MM-Google', $ga4->workspace_path);
        $this->assertSame('533626872', data_get($ga4->provider_config, 'property_id'));
        $this->assertSame('14399248676', data_get($ga4->provider_config, 'stream_id'));
        $this->assertSame('accounts/328441504', data_get($ga4->provider_config, 'analytics_account'));
        $this->assertSame('mm-brain-2026', data_get($ga4->provider_config, 'bigquery_project'));
        $this->assertSame(['production', 'brand:movingagain'], data_get($ga4->provider_config, 'tags'));
    }

    public function test_it_reports_changes_without_writing_them_in_dry_run_mode(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'wemove.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'wemove-com-au',
            'name' => 'wemove.com.au',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://wemove.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $configPath = storage_path('framework/testing/mm-google-sites-dry-run.json');
        File::ensureDirectoryExists(dirname($configPath));
        $configJson = json_encode([
            'sites' => [
                [
                    'key' => 'wemove',
                    'displayName' => 'wemove',
                    'websiteUrl' => 'https://wemove.com.au',
                    'analyticsAccount' => 'accounts/52062521',
                    'bigQueryProject' => 'mm-brain-2026',
                    'propertyId' => '399513187',
                    'streamId' => '5910310919',
                    'measurementId' => 'G-JVK6EDMC48',
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->assertIsString($configJson);
        File::put($configPath, $configJson);

        $this->assertSame(0, Artisan::call('analytics:sync-mm-google-ga4', [
            '--config-path' => $configPath,
            '--dry-run' => true,
        ]));

        $this->assertDatabaseMissing('property_analytics_sources', [
            'web_property_id' => $property->id,
            'provider' => 'ga4',
        ]);
    }
}
