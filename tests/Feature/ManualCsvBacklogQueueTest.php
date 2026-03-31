<?php

namespace Tests\Feature;

use App\Livewire\ManualCsvBacklogQueue;
use App\Models\AnalyticsInstallAudit;
use App\Models\Domain;
use App\Models\DomainSeoBaseline;
use App\Models\DomainTag;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\User;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class ManualCsvBacklogQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_manual_csv_backlog_queue(): void
    {
        $user = User::factory()->create();
        $manualCsvTag = DomainTag::create([
            'name' => 'automation.manual_csv_pending',
            'priority' => 68,
            'color' => '#ca8a04',
        ]);

        $pendingProperty = $this->makeProperty('csv-pending.example.au', 'CSV Pending Site');
        $this->attachRepository($pendingProperty);
        $pendingSource = $this->attachMatomo($pendingProperty, '701');
        $this->attachInstallAudit($pendingProperty, $pendingSource);
        $this->attachCoverage($pendingProperty, $pendingSource, 'domain_property', now()->subDay()->toDateString());
        $this->attachBaseline($pendingProperty, $pendingSource, 'matomo_api');
        $pendingProperty->primaryDomainModel()?->tags()->syncWithoutDetaching([$manualCsvTag->id]);

        $completeProperty = $this->makeProperty('complete.example.au', 'Complete Site');
        $this->attachRepository($completeProperty);
        $completeSource = $this->attachMatomo($completeProperty, '702');
        $this->attachInstallAudit($completeProperty, $completeSource);
        $this->attachCoverage($completeProperty, $completeSource, 'domain_property', now()->subDay()->toDateString());
        $this->attachBaseline($completeProperty, $completeSource, 'matomo_plus_manual_csv');
        $completeProperty->primaryDomainModel()?->tags()->syncWithoutDetaching([$manualCsvTag->id]);

        $sharedComplete = $this->makeProperty('shared.example.au', 'Shared Complete');
        $this->attachRepository($sharedComplete);
        $sharedCompleteSource = $this->attachMatomo($sharedComplete, '703');
        $this->attachInstallAudit($sharedComplete, $sharedCompleteSource);
        $this->attachCoverage($sharedComplete, $sharedCompleteSource, 'domain_property', now()->subDay()->toDateString());
        $this->attachBaseline($sharedComplete, $sharedCompleteSource, 'matomo_plus_manual_csv', now()->subMinute());

        $sharedPending = WebProperty::factory()->create([
            'slug' => 'shared-pending-site',
            'name' => 'Shared Pending',
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $sharedComplete->primary_domain_id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $sharedPending->id,
            'domain_id' => $sharedComplete->primary_domain_id,
            'usage_type' => 'primary',
            'is_canonical' => false,
        ]);
        $this->attachRepository($sharedPending);
        $sharedPendingSource = $this->attachMatomo($sharedPending, '704');
        $this->attachInstallAudit($sharedPending, $sharedPendingSource);
        $this->attachCoverage($sharedPending, $sharedPendingSource, 'domain_property', now()->subDay()->toDateString());
        $this->attachBaseline($sharedPending, $sharedPendingSource, 'matomo_api', now());
        $sharedPending->primaryDomainModel()?->tags()->syncWithoutDetaching([$manualCsvTag->id]);

        $properties = collect([
            $pendingProperty->fresh(),
            $completeProperty->fresh(),
            $sharedComplete->fresh(),
            $sharedPending->fresh(),
        ])
            ->filter(fn (?WebProperty $property): bool => $property instanceof WebProperty)
            ->values();

        $expectedNames = $properties
            ->filter(fn (WebProperty $property): bool => $property->automationCoverageSummary()['status'] === 'manual_csv_pending')
            ->map(fn (WebProperty $property): string => $property->name)
            ->sort()
            ->values()
            ->all();

        $expectedPendingDomains = $properties
            ->filter(fn (WebProperty $property): bool => $property->automationCoverageSummary()['status'] === 'manual_csv_pending')
            ->map(fn (WebProperty $property): ?string => $property->primaryDomainName())
            ->filter()
            ->unique()
            ->count();

        $response = $this->actingAs($user)->get('/manual-csv-backlog');

        $response->assertOk();
        $response->assertSee('Manual Search Console CSV Backlog');

        foreach ($expectedNames as $name) {
            $response->assertSee($name);
        }

        foreach ($properties->map(fn (WebProperty $property): string => $property->name)->diff($expectedNames) as $name) {
            $response->assertDontSee($name);
        }

        Livewire::test(ManualCsvBacklogQueue::class)
            ->assertViewHas('stats', function (array $stats) use ($expectedNames, $expectedPendingDomains): bool {
                return $stats['pending_properties'] === count($expectedNames)
                    && $stats['pending_domains'] === $expectedPendingDomains;
            })
            ->assertViewHas('pendingItems', function (Collection $items) use ($expectedNames): bool {
                $names = $items->map(fn (array $item): string => $item['property']->name)->all();

                sort($names);

                return $names === $expectedNames;
            });
    }

    public function test_operator_can_upload_search_console_zip_and_clear_manual_csv_backlog(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $manualCsvTag = DomainTag::create([
            'name' => 'automation.manual_csv_pending',
            'priority' => 68,
            'color' => '#ca8a04',
        ]);

        $property = $this->makeProperty('csv-pending.example.au', 'CSV Pending Site');
        $this->attachRepository($property);
        $source = $this->attachMatomo($property, '701');
        $this->attachInstallAudit($property, $source);
        $this->attachCoverage($property, $source, 'domain_property', now()->subDay()->toDateString());
        $this->attachBaseline($property, $source, 'matomo_api', now()->subMinute());
        $property->primaryDomainModel()?->tags()->syncWithoutDetaching([$manualCsvTag->id]);

        $zipPath = $this->makeSearchConsoleExportZip();
        $zipContents = file_get_contents($zipPath);

        $this->assertIsString($zipContents);

        Livewire::actingAs($user)
            ->test(ManualCsvBacklogQueue::class)
            ->set('evidenceArchives.'.$property->id, UploadedFile::fake()->createWithContent('page-indexing.zip', $zipContents))
            ->call('importEvidence', $property->id)
            ->assertHasNoErrors();

        Livewire::actingAs($user)
            ->test(ManualCsvBacklogQueue::class)
            ->assertViewHas('stats', fn (array $stats): bool => $stats['pending_properties'] === 0);

        $latestBaseline = $property->primaryDomainModel()?->latestSeoBaseline()->first();

        $this->assertNotNull($latestBaseline);
        $this->assertSame('matomo_plus_manual_csv', $latestBaseline->import_method);
        $this->assertSame(18, $latestBaseline->indexed_pages);
        $this->assertSame(186, $latestBaseline->not_indexed_pages);
        $this->assertSame(35, $latestBaseline->pages_with_redirect);
        $this->assertSame('complete', $property->fresh()->automationCoverageSummary()['status']);
        Storage::disk('local')->assertExists($latestBaseline->artifact_path);
    }

    public function test_upload_uses_the_target_property_baseline_on_shared_domains(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $manualCsvTag = DomainTag::create([
            'name' => 'automation.manual_csv_pending',
            'priority' => 68,
            'color' => '#ca8a04',
        ]);

        $domain = Domain::factory()->create([
            'domain' => 'shared-csv.example.au',
            'is_active' => true,
            'platform' => 'Astro',
        ]);

        $propertyA = WebProperty::factory()->create([
            'slug' => 'shared-property-a',
            'name' => 'Shared Property A',
            'status' => 'active',
            'property_type' => 'marketing_site',
            'primary_domain_id' => $domain->id,
        ]);

        $propertyB = WebProperty::factory()->create([
            'slug' => 'shared-property-b',
            'name' => 'Shared Property B',
            'status' => 'active',
            'property_type' => 'marketing_site',
            'primary_domain_id' => $domain->id,
        ]);

        foreach ([$propertyA, $propertyB] as $property) {
            WebPropertyDomain::create([
                'web_property_id' => $property->id,
                'domain_id' => $domain->id,
                'usage_type' => 'primary',
                'is_canonical' => $property->id === $propertyA->id,
            ]);
            $this->attachRepository($property);
            $property->primaryDomainModel()?->tags()->syncWithoutDetaching([$manualCsvTag->id]);
        }

        $sourceA = $this->attachMatomo($propertyA, '810');
        $sourceB = $this->attachMatomo($propertyB, '811');
        $this->attachInstallAudit($propertyA, $sourceA);
        $this->attachInstallAudit($propertyB, $sourceB);
        $this->attachCoverage($propertyA, $sourceA, 'domain_property', now()->subDay()->toDateString());
        $this->attachCoverage($propertyB, $sourceB, 'domain_property', now()->subDay()->toDateString());
        $this->attachBaseline($propertyA, $sourceA, 'matomo_api', now()->subMinutes(2), 10, 100);
        $this->attachBaseline($propertyB, $sourceB, 'matomo_api', now()->subMinute(), 999, 5000);

        $zipPath = $this->makeSearchConsoleExportZip();
        $zipContents = file_get_contents($zipPath);

        $this->assertIsString($zipContents);

        Livewire::actingAs($user)
            ->test(ManualCsvBacklogQueue::class)
            ->set('evidenceArchives.'.$propertyB->id, UploadedFile::fake()->createWithContent('page-indexing.zip', $zipContents))
            ->call('importEvidence', $propertyB->id)
            ->assertHasNoErrors();

        $propertyBLatestBaseline = $propertyB->fresh()->latestPropertySeoBaselineRecord();

        $this->assertNotNull($propertyBLatestBaseline);
        $this->assertSame('matomo_plus_manual_csv', $propertyBLatestBaseline->import_method);
        $this->assertSame(999.0, $propertyBLatestBaseline->clicks);
        $this->assertSame(5000.0, $propertyBLatestBaseline->impressions);
        $this->assertSame('complete', $propertyB->fresh()->automationCoverageSummary()['status']);
        $this->assertSame('manual_csv_pending', $propertyA->fresh()->automationCoverageSummary()['status']);
    }

    private function makeProperty(string $domainName, string $name): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'is_active' => true,
            'platform' => 'Astro',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => str($domainName)->replace('.', '-')->toString(),
            'name' => $name,
            'status' => 'active',
            'property_type' => 'marketing_site',
            'primary_domain_id' => $domain->id,
        ]);

        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        return $property;
    }

    private function attachRepository(WebProperty $property): void
    {
        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_provider' => 'local',
            'repo_name' => $property->slug.'-repo',
            'local_path' => '/tmp/'.$property->slug,
            'is_primary' => true,
            'status' => 'active',
        ]);
    }

    private function attachMatomo(WebProperty $property, string $externalId): PropertyAnalyticsSource
    {
        return PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => $externalId,
            'external_name' => $property->name,
            'is_primary' => true,
            'status' => 'active',
        ]);
    }

    private function attachInstallAudit(WebProperty $property, PropertyAnalyticsSource $source): void
    {
        AnalyticsInstallAudit::create([
            'property_analytics_source_id' => $source->id,
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => $source->external_id,
            'external_name' => $property->name,
            'install_verdict' => 'installed_match',
            'best_url' => 'https://'.$property->primaryDomainName().'/',
            'summary' => 'Tracker matches the linked Matomo site.',
            'checked_at' => now(),
            'raw_payload' => ['verdict' => 'installed_match'],
        ]);
    }

    private function attachCoverage(WebProperty $property, PropertyAnalyticsSource $source, string $mappingState, ?string $latestMetricDate): void
    {
        SearchConsoleCoverageStatus::create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'source_provider' => 'matomo',
            'matomo_site_id' => $source->external_id,
            'matomo_site_name' => $property->name,
            'mapping_state' => $mappingState,
            'property_uri' => $mappingState === 'domain_property'
                ? 'sc-domain:'.$property->primaryDomainName()
                : 'https://'.$property->primaryDomainName().'/',
            'property_type' => $mappingState === 'domain_property' ? 'domain' : 'url-prefix',
            'latest_metric_date' => $latestMetricDate,
            'checked_at' => now(),
        ]);
    }

    private function attachBaseline(
        WebProperty $property,
        PropertyAnalyticsSource $source,
        string $importMethod,
        ?\Illuminate\Support\Carbon $capturedAt = null,
        int $clicks = 10,
        int $impressions = 100
    ): void {
        DomainSeoBaseline::create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'baseline_type' => 'search_console',
            'captured_at' => $capturedAt ?? now(),
            'source_provider' => 'search_console',
            'matomo_site_id' => $source->external_id,
            'search_console_property_uri' => 'sc-domain:'.$property->primaryDomainName(),
            'search_type' => 'web',
            'import_method' => $importMethod,
            'clicks' => $clicks,
            'impressions' => $impressions,
            'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : 0.0,
            'average_position' => 12.4,
        ]);
    }

    private function makeSearchConsoleExportZip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'sc-export-');
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
