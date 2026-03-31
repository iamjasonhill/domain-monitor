<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\SearchConsoleIssueSnapshot;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class ImportSearchConsoleIssueSnapshotCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_search_console_issue_detail_drilldown_zip(): void
    {
        Storage::fake('local');

        $property = $this->makeProperty('drilldown-site', 'drilldown.example.au', '42');
        $zipPath = $this->makeDrilldownExportZip(
            'Page with redirect',
            [
                ['https://drilldown.example.au/', '2026-03-28'],
                ['http://drilldown.example.au/', '2026-03-27'],
            ]
        );

        $exitCode = Artisan::call('analytics:import-search-console-issue-detail', [
            'property' => $property->slug,
            'path' => $zipPath,
            '--captured-by' => 'test-suite',
        ]);

        $this->assertSame(0, $exitCode);

        $snapshot = SearchConsoleIssueSnapshot::query()->firstOrFail();
        $this->assertSame('page_with_redirect_in_sitemap', $snapshot->issue_class);
        $this->assertSame('gsc_drilldown_zip', $snapshot->capture_method);
        $this->assertSame(2, $snapshot->affected_url_count);
        $this->assertSame(['https://drilldown.example.au/', 'http://drilldown.example.au/'], $snapshot->sample_urls);
        $this->assertSame('search_console_page_indexing_drilldown', $snapshot->source_report);
        Storage::disk('local')->assertExists($snapshot->artifact_path);
    }

    public function test_it_treats_na_last_crawled_values_as_null_in_drilldown_zip(): void
    {
        Storage::fake('local');

        $property = $this->makeProperty('na-crawl-site', 'na-crawl.example.au', '43');
        $zipPath = $this->makeDrilldownExportZip(
            'Discovered - currently not indexed',
            [
                ['https://na-crawl.example.au/new-page/', 'N/A'],
            ]
        );

        $exitCode = Artisan::call('analytics:import-search-console-issue-detail', [
            'property' => $property->slug,
            'path' => $zipPath,
            '--captured-by' => 'test-suite',
        ]);

        $this->assertSame(0, $exitCode);

        $snapshot = SearchConsoleIssueSnapshot::query()->firstOrFail();
        $this->assertSame('discovered_currently_not_indexed', $snapshot->issue_class);
        $this->assertNull(data_get($snapshot->examples, '0.last_crawled'));
        $this->assertNull(data_get($snapshot->issueEvidence(), 'examples.0.last_crawled'));
    }

    public function test_it_imports_search_console_api_evidence_json(): void
    {
        Storage::fake('local');

        $property = $this->makeProperty('api-site', 'api.example.au', '84');
        $jsonPath = $this->makeApiEvidenceJson([
            'source_report' => 'search_console_api',
            'source_property' => 'sc-domain:api.example.au',
            'url_inspection' => [
                'coverageState' => 'Blocked by robots.txt',
                'robotsTxtState' => 'BLOCKED',
                'lastCrawlTime' => '2026-03-28T00:00:00Z',
            ],
            'sitemaps' => [
                ['path' => 'https://api.example.au/sitemap_index.xml', 'warnings' => 0, 'errors' => 0],
            ],
            'referring_urls' => ['https://api.example.au/sitemap_index.xml'],
            'canonical_state' => [
                'google_canonical' => 'https://api.example.au/blocked-page/',
                'user_canonical' => 'https://api.example.au/blocked-page/',
            ],
        ]);

        $exitCode = Artisan::call('analytics:import-search-console-api-evidence', [
            'property' => $property->slug,
            'issueClass' => 'blocked_by_robots_in_indexing',
            'path' => $jsonPath,
            '--capture-method' => 'gsc_api',
            '--captured-by' => 'test-suite',
        ]);

        $this->assertSame(0, $exitCode);

        $snapshot = SearchConsoleIssueSnapshot::query()->firstOrFail();
        $this->assertSame('blocked_by_robots_in_indexing', $snapshot->issue_class);
        $this->assertSame('gsc_api', $snapshot->capture_method);
        $this->assertSame('BLOCKED', data_get($snapshot->normalized_payload, 'url_inspection.robotsTxtState'));
        $this->assertSame(['https://api.example.au/sitemap_index.xml'], data_get($snapshot->normalized_payload, 'referring_urls'));
        Storage::disk('local')->assertExists($snapshot->artifact_path);
    }

    public function test_it_rejects_unknown_issue_label_in_drilldown_zip(): void
    {
        Storage::fake('local');

        $property = $this->makeProperty('unsupported-site', 'unsupported.example.au', '99');
        $zipPath = $this->makeDrilldownExportZip(
            'Completely unsupported issue',
            [
                ['https://unsupported.example.au/example/', '2026-03-28'],
            ]
        );

        $exitCode = Artisan::call('analytics:import-search-console-issue-detail', [
            'property' => $property->slug,
            'path' => $zipPath,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Unsupported Search Console issue label', Artisan::output());
        $this->assertSame(0, SearchConsoleIssueSnapshot::query()->count());
    }

    private function makeProperty(string $slug, string $domainName, string $matomoSiteId): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'is_active' => true,
        ]);

        $property = WebProperty::factory()->create([
            'slug' => $slug,
            'name' => Str::of($slug)->replace('-', ' ')->title()->toString(),
            'primary_domain_id' => $domain->id,
            'status' => 'active',
            'property_type' => 'website',
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'production',
            'is_canonical' => true,
        ]);

        $source = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => $matomoSiteId,
            'external_name' => $property->name,
            'is_primary' => true,
            'status' => 'active',
        ]);

        SearchConsoleCoverageStatus::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'source_provider' => 'matomo',
            'matomo_site_id' => $matomoSiteId,
            'matomo_site_name' => $property->name,
            'mapping_state' => 'domain_property',
            'property_uri' => 'sc-domain:'.$domainName,
            'property_type' => 'domain',
            'latest_metric_date' => now()->subDay()->toDateString(),
            'checked_at' => now(),
        ]);

        return $property;
    }

    /**
     * @param  array<int, array{0:string,1:string}>  $rows
     */
    private function makeDrilldownExportZip(string $issueLabel, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gsc-drilldown-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('Metadata.csv', implode("\n", [
            'Property,Value',
            'Sitemap,All known pages',
            'Issue,'.$issueLabel,
        ]));
        $zip->addFromString('Chart.csv', implode("\n", [
            'Date,Affected pages',
            '2026-03-28,'.count($rows),
        ]));
        $tableRows = ['URL,Last crawled'];
        foreach ($rows as [$url, $lastCrawled]) {
            $tableRows[] = $url.','.$lastCrawled;
        }
        $zip->addFromString('Table.csv', implode("\n", $tableRows));
        $zip->close();

        return $path;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function makeApiEvidenceJson(array $payload): string
    {
        $path = tempnam(sys_get_temp_dir(), 'gsc-api-');
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $path;
    }
}
