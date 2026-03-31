<?php

namespace Tests\Feature;

use App\Models\AnalyticsInstallAudit;
use App\Models\Domain;
use App\Models\DomainSeoBaseline;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class ImportSearchConsoleEvidenceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_manual_search_console_evidence_for_a_property(): void
    {
        Storage::fake('local');

        $domain = Domain::factory()->create([
            'domain' => 'moveroo.com.au',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => 'moveroo-website',
            'name' => 'Moveroo website',
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

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_provider' => 'local',
            'repo_name' => 'moveroo-website-repo',
            'local_path' => '/tmp/moveroo-website',
            'is_primary' => true,
            'status' => 'active',
        ]);

        $source = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '6',
            'external_name' => 'Moveroo website',
            'is_primary' => true,
            'status' => 'active',
        ]);

        AnalyticsInstallAudit::create([
            'property_analytics_source_id' => $source->id,
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => '6',
            'external_name' => 'Moveroo website',
            'install_verdict' => 'installed_match',
            'best_url' => 'https://moveroo.com.au/',
            'summary' => 'Tracker matches the linked Matomo site.',
            'checked_at' => now(),
            'raw_payload' => ['verdict' => 'installed_match'],
        ]);

        SearchConsoleCoverageStatus::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'source_provider' => 'matomo',
            'matomo_site_id' => '6',
            'matomo_site_name' => 'Moveroo website',
            'mapping_state' => 'domain_property',
            'property_uri' => 'sc-domain:moveroo.com.au',
            'property_type' => 'domain',
            'latest_metric_date' => now()->subDay()->toDateString(),
            'checked_at' => now(),
        ]);

        DomainSeoBaseline::create([
            'domain_id' => $domain->id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'baseline_type' => 'search_console',
            'captured_at' => now()->subDay(),
            'source_provider' => 'matomo',
            'matomo_site_id' => '6',
            'search_console_property_uri' => 'sc-domain:moveroo.com.au',
            'search_type' => 'web',
            'import_method' => 'matomo_api',
            'clicks' => 287,
            'impressions' => 8200,
            'ctr' => 0.034,
            'average_position' => 12.8,
        ]);

        $zipPath = $this->makeSearchConsoleExportZip();

        $exitCode = Artisan::call('analytics:import-search-console-evidence', [
            'property' => 'moveroo-website',
            'path' => $zipPath,
            '--captured-by' => 'test-suite',
        ]);

        $this->assertSame(0, $exitCode);

        $latestBaseline = $domain->latestSeoBaseline()->first();

        $this->assertNotNull($latestBaseline);
        $this->assertSame('matomo_plus_manual_csv', $latestBaseline->import_method);
        $this->assertSame('test-suite', $latestBaseline->captured_by);
        $this->assertSame(287.0, $latestBaseline->clicks);
        $this->assertSame(8200.0, $latestBaseline->impressions);
        $this->assertSame(18, $latestBaseline->indexed_pages);
        $this->assertSame(186, $latestBaseline->not_indexed_pages);
        $this->assertSame(35, $latestBaseline->pages_with_redirect);
        $this->assertSame(12, $latestBaseline->not_found_404);
        $this->assertSame(23, $latestBaseline->duplicate_without_user_selected_canonical);
        Storage::disk('local')->assertExists($latestBaseline->artifact_path);
        $this->assertSame('complete', $property->fresh()->automationCoverageSummary()['status']);
    }

    private function makeSearchConsoleExportZip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sc-evidence-');
        $zipPath = $path.'.zip';

        @unlink($path);

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('Chart.csv', implode("\n", [
            'Date,Not indexed,Indexed,Impressions',
            '2026-03-29,188,18,140',
            '2026-03-30,186,18,160',
        ]));
        $zip->addFromString('Critical issues.csv', implode("\n", [
            'Reason,Source,Validation,Pages',
            'Page with redirect,Website,Not Started,35',
            'Duplicate without user-selected canonical,Website,Not Started,23',
            'Not found (404),Website,Not Started,12',
            'Alternative page with proper canonical tag,Website,Not Started,4',
            'Crawled - currently not indexed,Google systems,Not Started,90',
        ]));
        $zip->addFromString('Non-critical issues.csv', "Reason,Source,Validation,Pages\n");
        $zip->addFromString('Metadata.csv', implode("\n", [
            'Property,Value',
            'Sitemap,All known pages',
        ]));
        $zip->close();

        return $zipPath;
    }
}
