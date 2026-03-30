<?php

namespace Tests\Feature;

use App\Models\AnalyticsInstallAudit;
use App\Models\Domain;
use App\Models\DomainSeoBaseline;
use App\Models\DomainTag;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncCoverageTagsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_required_complete_and_gap_tags_onto_primary_domains(): void
    {
        $this->setCoverageTagConfig();

        $completeDomain = Domain::factory()->create([
            'domain' => 'complete.example.au',
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
            'platform' => 'Astro',
        ]);
        $completeProperty = WebProperty::factory()->create([
            'slug' => 'complete-site',
            'name' => 'Complete Site',
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $completeDomain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $completeProperty->id,
            'domain_id' => $completeDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);
        PropertyRepository::create([
            'web_property_id' => $completeProperty->id,
            'repo_name' => 'complete-site-repo',
            'local_path' => '/tmp/complete-site-repo',
            'framework' => 'Astro',
            'is_primary' => true,
        ]);
        $completeSource = PropertyAnalyticsSource::create([
            'web_property_id' => $completeProperty->id,
            'provider' => 'matomo',
            'external_id' => '101',
            'external_name' => 'Complete Site',
            'is_primary' => true,
            'status' => 'active',
        ]);
        AnalyticsInstallAudit::create([
            'property_analytics_source_id' => $completeSource->id,
            'web_property_id' => $completeProperty->id,
            'provider' => 'matomo',
            'external_id' => '101',
            'external_name' => 'Complete Site',
            'install_verdict' => 'installed_match',
            'summary' => 'Tracker matches',
            'checked_at' => now(),
        ]);
        SearchConsoleCoverageStatus::create([
            'domain_id' => $completeDomain->id,
            'web_property_id' => $completeProperty->id,
            'property_analytics_source_id' => $completeSource->id,
            'source_provider' => 'matomo',
            'matomo_site_id' => '101',
            'matomo_site_name' => 'Complete Site',
            'mapping_state' => 'domain_property',
            'property_uri' => 'sc-domain:complete.example.au',
            'property_type' => 'domain',
            'latest_metric_date' => now()->subDay()->toDateString(),
            'checked_at' => now(),
        ]);
        DomainSeoBaseline::create([
            'domain_id' => $completeDomain->id,
            'web_property_id' => $completeProperty->id,
            'property_analytics_source_id' => $completeSource->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'source_provider' => 'search_console',
            'matomo_site_id' => '101',
            'search_console_property_uri' => 'sc-domain:complete.example.au',
            'search_type' => 'web',
            'import_method' => 'matomo_plus_manual_csv',
            'clicks' => 10,
            'impressions' => 100,
            'ctr' => 0.1,
            'average_position' => 12.4,
        ]);

        $gapDomain = Domain::factory()->create([
            'domain' => 'gap.example.au',
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
            'platform' => 'WordPress',
        ]);
        $gapProperty = WebProperty::factory()->create([
            'slug' => 'gap-site',
            'name' => 'Gap Site',
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $gapDomain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $gapProperty->id,
            'domain_id' => $gapDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $csvPendingDomain = Domain::factory()->create([
            'domain' => 'csv-pending.example.au',
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
            'platform' => 'Astro',
        ]);
        $csvPendingProperty = WebProperty::factory()->create([
            'slug' => 'csv-pending-site',
            'name' => 'CSV Pending Site',
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $csvPendingDomain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $csvPendingProperty->id,
            'domain_id' => $csvPendingDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);
        $csvPendingSource = $this->attachRepositoryAndCoverage($csvPendingProperty, '102');
        $this->attachBaselineForSource($csvPendingProperty, $csvPendingSource, 'matomo_api');

        $excludedDomain = Domain::factory()->create([
            'domain' => 'parked.example.au',
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
            'platform' => 'Astro',
        ]);
        $excludedProperty = WebProperty::factory()->create([
            'slug' => 'parked-site',
            'name' => 'Parked Site',
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $excludedDomain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $excludedProperty->id,
            'domain_id' => $excludedDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $wrongGapTag = DomainTag::create([
            'name' => 'coverage.gap',
            'priority' => 85,
            'color' => '#dc2626',
        ]);
        $wrongAutomationTag = DomainTag::create([
            'name' => 'automation.gap',
            'priority' => 65,
            'color' => '#dc2626',
        ]);
        $requiredTag = DomainTag::create([
            'name' => 'coverage.required',
            'priority' => 90,
            'color' => '#2563eb',
        ]);
        $manualExclusionTag = DomainTag::create([
            'name' => 'coverage.excluded',
            'priority' => 95,
            'color' => '#6b7280',
        ]);
        $excludedDomain->tags()->sync([$requiredTag->id, $wrongGapTag->id, $wrongAutomationTag->id, $manualExclusionTag->id]);
        $completeDomain->tags()->sync([$requiredTag->id, $wrongGapTag->id, $wrongAutomationTag->id]);

        $this->assertSame('complete', $completeProperty->fresh()->fullCoverageSummary()['status']);
        $this->assertSame('complete', $completeProperty->fresh()->automationCoverageSummary()['status']);
        $this->assertSame('manual_csv_pending', $csvPendingProperty->fresh()->automationCoverageSummary()['status']);

        $this->assertSame(0, Artisan::call('coverage:sync-tags'));

        $this->assertSame(
            ['automation.complete', 'automation.required', 'coverage.complete', 'coverage.required'],
            $completeDomain->fresh()->tags()->orderBy('name')->pluck('name')->all()
        );

        $this->assertSame(
            ['automation.gap', 'automation.required', 'coverage.gap', 'coverage.required'],
            $gapDomain->fresh()->tags()->orderBy('name')->pluck('name')->all()
        );

        $this->assertSame(
            ['automation.gap', 'automation.manual_csv_pending', 'automation.required', 'coverage.complete', 'coverage.required'],
            $csvPendingDomain->fresh()->tags()->orderBy('name')->pluck('name')->all()
        );

        $this->assertSame(['coverage.excluded'], $excludedDomain->fresh()->tags()->pluck('name')->all());
    }

    public function test_dry_run_does_not_mutate_tags(): void
    {
        $this->setCoverageTagConfig();

        $domain = Domain::factory()->create([
            'domain' => 'dry-run.example.au',
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
        ]);
        $property = WebProperty::factory()->create([
            'slug' => 'dry-run-site',
            'name' => 'Dry Run Site',
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $domain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $this->assertSame(0, Artisan::call('coverage:sync-tags', ['--dry-run' => true]));

        $this->assertDatabaseCount('domain_tags', 0);
        $this->assertSame([], $domain->fresh()->tags()->pluck('name')->all());
    }

    public function test_domain_option_limits_mutation_to_named_primary_domains(): void
    {
        $this->setCoverageTagConfig();

        $targetDomain = $this->createCompleteCoverageProperty('target.example.au', 'Target Site', '201');
        $untouchedDomain = Domain::factory()->create([
            'domain' => 'untouched.example.au',
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
            'platform' => 'WordPress',
        ]);
        $untouchedProperty = WebProperty::factory()->create([
            'slug' => 'untouched-site',
            'name' => 'Untouched Site',
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $untouchedDomain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $untouchedProperty->id,
            'domain_id' => $untouchedDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $this->assertSame(0, Artisan::call('coverage:sync-tags', [
            '--domain' => ['target.example.au'],
        ]));

        $this->assertSame(
            ['automation.complete', 'automation.required', 'coverage.complete', 'coverage.required'],
            $targetDomain->fresh()->tags()->orderBy('name')->pluck('name')->all()
        );
        $this->assertSame([], $untouchedDomain->fresh()->tags()->pluck('name')->all());
    }

    public function test_domain_option_with_no_matches_does_not_create_tags_or_mutate_domains(): void
    {
        $this->setCoverageTagConfig();

        $domain = Domain::factory()->create([
            'domain' => 'existing.example.au',
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
            'platform' => 'Astro',
        ]);
        $property = WebProperty::factory()->create([
            'slug' => 'existing-site',
            'name' => 'Existing Site',
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $domain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $this->assertSame(0, Artisan::call('coverage:sync-tags', [
            '--domain' => ['missing.example.au'],
        ]));

        $this->assertDatabaseCount('domain_tags', 0);
        $this->assertSame([], $domain->fresh()->tags()->pluck('name')->all());
    }

    public function test_shared_primary_domain_uses_canonical_property_when_syncing_tags(): void
    {
        $this->setCoverageTagConfig();

        $domain = Domain::factory()->create([
            'domain' => 'shared.example.au',
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
            'platform' => 'WordPress',
        ]);

        $nonCanonicalGapProperty = WebProperty::factory()->create([
            'slug' => 'alpha-gap-site',
            'name' => 'Alpha Gap Site',
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $domain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $nonCanonicalGapProperty->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => false,
        ]);

        $canonicalCompleteProperty = WebProperty::factory()->create([
            'slug' => 'zeta-complete-site',
            'name' => 'Zeta Complete Site',
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $domain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $canonicalCompleteProperty->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);
        $canonicalSource = $this->attachRepositoryAndCoverage($canonicalCompleteProperty, '301');
        $this->attachBaselineForSource($canonicalCompleteProperty, $canonicalSource, 'matomo_plus_manual_csv');

        $this->assertSame(0, Artisan::call('coverage:sync-tags'));

        $this->assertSame(
            ['automation.complete', 'automation.required', 'coverage.complete', 'coverage.required'],
            $domain->fresh()->tags()->orderBy('name')->pluck('name')->all()
        );
    }

    private function setCoverageTagConfig(): void
    {
        config()->set('domain_monitor.coverage_tags', [
            'manual_exclusion_tag' => [
                'name' => 'coverage.excluded',
                'priority' => 95,
                'color' => '#6b7280',
                'description' => 'Excluded',
            ],
            'tags' => [
                'required' => [
                    'name' => 'coverage.required',
                    'priority' => 90,
                    'color' => '#2563eb',
                    'description' => 'Required',
                ],
                'complete' => [
                    'name' => 'coverage.complete',
                    'priority' => 80,
                    'color' => '#16a34a',
                    'description' => 'Complete',
                ],
                'gap' => [
                    'name' => 'coverage.gap',
                    'priority' => 85,
                    'color' => '#dc2626',
                    'description' => 'Gap',
                ],
            ],
            'automation_tags' => [
                'required' => [
                    'name' => 'automation.required',
                    'priority' => 70,
                    'color' => '#7c3aed',
                    'description' => 'Automation required',
                ],
                'complete' => [
                    'name' => 'automation.complete',
                    'priority' => 60,
                    'color' => '#16a34a',
                    'description' => 'Automation complete',
                ],
                'gap' => [
                    'name' => 'automation.gap',
                    'priority' => 65,
                    'color' => '#dc2626',
                    'description' => 'Automation gap',
                ],
                'manual_csv_pending' => [
                    'name' => 'automation.manual_csv_pending',
                    'priority' => 68,
                    'color' => '#ca8a04',
                    'description' => 'Manual CSV pending',
                ],
            ],
        ]);
    }

    private function createCompleteCoverageProperty(string $domainName, string $propertyName, string $matomoId): Domain
    {
        $domain = Domain::factory()->create([
            'domain' => $domainName,
            'is_active' => true,
            'dns_config_name' => 'DNS Hosting',
            'platform' => 'Astro',
        ]);
        $property = WebProperty::factory()->create([
            'slug' => str($domainName)->replace('.', '-')->toString(),
            'name' => $propertyName,
            'status' => 'active',
            'property_type' => 'website',
            'primary_domain_id' => $domain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $property->id,
            'domain_id' => $domain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $source = $this->attachRepositoryAndCoverage($property, $matomoId);
        $this->attachBaselineForSource($property, $source, 'matomo_plus_manual_csv');

        return $domain;
    }

    private function attachRepositoryAndCoverage(WebProperty $property, string $matomoId): PropertyAnalyticsSource
    {
        PropertyRepository::create([
            'web_property_id' => $property->id,
            'repo_name' => $property->slug.'-repo',
            'local_path' => '/tmp/'.$property->slug,
            'framework' => 'Astro',
            'is_primary' => true,
        ]);
        $source = PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => $matomoId,
            'external_name' => $property->name,
            'is_primary' => true,
            'status' => 'active',
        ]);
        AnalyticsInstallAudit::create([
            'property_analytics_source_id' => $source->id,
            'web_property_id' => $property->id,
            'provider' => 'matomo',
            'external_id' => $matomoId,
            'external_name' => $property->name,
            'install_verdict' => 'installed_match',
            'summary' => 'Tracker matches',
            'checked_at' => now(),
        ]);
        SearchConsoleCoverageStatus::create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'source_provider' => 'matomo',
            'matomo_site_id' => $matomoId,
            'matomo_site_name' => $property->name,
            'mapping_state' => 'domain_property',
            'property_uri' => 'sc-domain:'.$property->primaryDomainName(),
            'property_type' => 'domain',
            'latest_metric_date' => now()->subDay()->toDateString(),
            'checked_at' => now(),
        ]);

        return $source;
    }

    private function attachBaselineForSource(WebProperty $property, PropertyAnalyticsSource $source, string $importMethod): void
    {
        DomainSeoBaseline::create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'baseline_type' => 'search_console',
            'captured_at' => now(),
            'source_provider' => 'search_console',
            'matomo_site_id' => $source->external_id,
            'search_console_property_uri' => 'sc-domain:'.$property->primaryDomainName(),
            'search_type' => 'web',
            'import_method' => $importMethod,
            'clicks' => 10,
            'impressions' => 100,
            'ctr' => 0.1,
            'average_position' => 12.4,
        ]);
    }
}
