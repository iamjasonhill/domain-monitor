<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ArchiveLegacyMatomoSourcesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_reports_matomo_archive_and_ga4_promotion_without_writing(): void
    {
        $property = $this->makeProperty('dry-run.example.au', 'Dry Run Site');
        $matomo = $this->attachMatomo($property, '101');
        $ga4 = $this->attachGa4($property, 'G-DRYRUN01', false);

        $exitCode = Artisan::call('analytics:archive-legacy-matomo-sources');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('dry-run-example-au', $output);
        $this->assertStringContainsString('101', $output);
        $this->assertStringContainsString('G-DRYRUN01', $output);
        $this->assertStringContainsString('Dry run complete', $output);

        $this->assertTrue($matomo->fresh()->is_primary);
        $this->assertSame('active', $matomo->fresh()->status);
        $this->assertFalse($ga4->fresh()->is_primary);
    }

    public function test_write_archives_matomo_sources_and_promotes_valid_ga4(): void
    {
        $property = $this->makeProperty('write.example.au', 'Write Site');
        $matomo = $this->attachMatomo($property, '201');
        $ga4 = $this->attachGa4($property, 'G-WRITE001', false);

        $exitCode = Artisan::call('analytics:archive-legacy-matomo-sources', [
            '--write' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $matomo->refresh();
        $ga4->refresh();

        $this->assertFalse($matomo->is_primary);
        $this->assertSame('archived', $matomo->status);
        $this->assertStringContainsString('legacy archive/backfill only', (string) $matomo->notes);
        $this->assertTrue($ga4->is_primary);
        $this->assertSame('complete', $property->fresh()->automationCoverageSummary()['status']);
    }

    public function test_write_archives_matomo_without_hiding_missing_ga4_gap(): void
    {
        $property = $this->makeProperty('no-ga4.example.au', 'No GA4 Site');
        $matomo = $this->attachMatomo($property, '301');

        $exitCode = Artisan::call('analytics:archive-legacy-matomo-sources', [
            '--write' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('ga4_gap', $output);

        $matomo->refresh();

        $this->assertFalse($matomo->is_primary);
        $this->assertSame('archived', $matomo->status);
        $this->assertSame('needs_ga4_sync', $property->fresh()->automationCoverageSummary()['status']);
    }

    private function makeProperty(string $domainName, string $propertyName): WebProperty
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'is_active' => true,
            'platform' => 'Astro',
        ]);

        $property = WebProperty::factory()->create([
            'slug' => str($domainName)->replace('.', '-')->toString(),
            'name' => $propertyName,
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

        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => $property->slug.'-repo',
            'local_path' => '/tmp/'.$property->slug,
            'framework' => 'Astro',
            'is_primary' => true,
            'status' => 'active',
        ]);

        return $property;
    }

    private function attachMatomo(WebProperty $property, string $externalId): PropertyAnalyticsSource
    {
        return PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => $externalId,
            'external_name' => $property->name.' Matomo',
            'is_primary' => true,
            'status' => 'active',
        ]);
    }

    private function attachGa4(WebProperty $property, string $measurementId, bool $isPrimary): PropertyAnalyticsSource
    {
        $source = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'ga4',
            'external_id' => $measurementId,
            'external_name' => $property->name,
            'is_primary' => $isPrimary,
            'status' => 'active',
            'provider_config' => [
                'measurement_id' => $measurementId,
                'source_system' => 'MM-Google',
                'provisioning_state' => 'switch_ready',
                'switch_ready' => true,
            ],
        ]);

        SearchConsoleCoverageStatus::create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'source_provider' => 'mm-google',
            'matomo_site_id' => $measurementId,
            'matomo_site_name' => $property->name,
            'mapping_state' => 'domain_property',
            'property_uri' => 'sc-domain:'.$property->primaryDomainName(),
            'property_type' => 'domain',
            'latest_metric_date' => now()->subDay()->toDateString(),
            'checked_at' => now(),
        ]);

        $property->seoBaselines()->create([
            'domain_id' => $property->primary_domain_id,
            'property_analytics_source_id' => $source->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'source_provider' => 'search_console',
            'matomo_site_id' => $measurementId,
            'search_console_property_uri' => 'sc-domain:'.$property->primaryDomainName(),
            'search_type' => 'web',
            'import_method' => 'mm_google_export',
            'clicks' => 10,
            'impressions' => 100,
            'ctr' => 0.1,
            'average_position' => 12.4,
        ]);

        return $source;
    }
}
