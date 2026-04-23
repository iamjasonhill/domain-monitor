<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\DomainSeoBaseline;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SyncMmGoogleSearchConsoleExportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_mm_google_search_console_exports_into_domain_monitor(): void
    {
        $domain = Domain::factory()->create([
            'domain' => 'moveroo.com.au',
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'moveroo-website',
            'name' => 'Moveroo Website',
            'site_key' => 'moveroo',
            'status' => 'active',
            'primary_domain_id' => $domain->id,
            'production_url' => 'https://moveroo.com.au',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $path = storage_path('framework/testing/mm-google-search-console-export.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'sourceSystem' => 'mm-google',
            'contract' => 'search-console-coverage-baseline-v1',
            'generatedAt' => '2026-04-23T03:00:00Z',
            'coverageRecords' => [
                [
                    'siteKey' => 'moveroo',
                    'displayName' => 'Moveroo Website',
                    'websiteUrl' => 'https://moveroo.com.au',
                    'coverageStatus' => 'search_console_ready',
                    'expectedPropertyType' => 'domain',
                    'expectedPropertyIdentifier' => 'sc-domain:moveroo.com.au',
                    'matchedPropertyIdentifier' => 'sc-domain:moveroo.com.au',
                    'lastCheckedAt' => '2026-04-23T03:00:00Z',
                ],
            ],
            'baselineRecords' => [
                [
                    'siteKey' => 'moveroo',
                    'displayName' => 'Moveroo Website',
                    'websiteUrl' => 'https://moveroo.com.au',
                    'propertyIdentifier' => 'sc-domain:moveroo.com.au',
                    'baselineStatus' => 'search_console_ready',
                    'readinessStatus' => 'ready',
                    'lastCheckedAt' => '2026-04-23T03:00:00Z',
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->assertSame(0, Artisan::call('analytics:sync-mm-google-search-console-export', [
            'path' => $path,
        ]));

        $coverage = SearchConsoleCoverageStatus::query()->firstOrFail();
        $baseline = DomainSeoBaseline::query()->firstOrFail();

        $this->assertSame('mm-google', $coverage->source_provider);
        $this->assertSame('moveroo', $coverage->matomo_site_id);
        $this->assertSame('domain_property', $coverage->mapping_state);
        $this->assertSame('sc-domain:moveroo.com.au', $coverage->property_uri);
        $this->assertSame('search_console_ready', data_get($coverage->raw_payload, 'coverageStatus'));

        $this->assertSame('mm-google', $baseline->source_provider);
        $this->assertSame('moveroo', $baseline->matomo_site_id);
        $this->assertSame('mm_google_export', $baseline->import_method);
        $this->assertSame('sc-domain:moveroo.com.au', $baseline->search_console_property_uri);
        $this->assertSame('search_console_ready', data_get($baseline->raw_payload, 'baselineStatus'));

        $property->refresh();
        $summary = $property->searchConsoleCoverageSummary();

        $this->assertSame('covered', $summary['status']);
        $this->assertSame('Covered', $summary['label']);
        $this->assertStringContainsString('MM-Google evidence', $summary['reason']);
    }
}
