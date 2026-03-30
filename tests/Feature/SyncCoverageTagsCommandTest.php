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
use Tests\TestCase;

class SyncCoverageTagsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_required_complete_and_gap_tags_onto_primary_domains(): void
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
        ]);

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
            'import_method' => 'manual',
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
        $excludedDomain->tags()->sync([$requiredTag->id, $wrongGapTag->id, $manualExclusionTag->id]);
        $completeDomain->tags()->sync([$requiredTag->id, $wrongGapTag->id]);

        $this->artisan('coverage:sync-tags')
            ->assertSuccessful();

        $this->assertSame(
            ['coverage.complete', 'coverage.required'],
            $completeDomain->fresh()->tags()->orderBy('name')->pluck('name')->all()
        );

        $this->assertSame(
            ['coverage.gap', 'coverage.required'],
            $gapDomain->fresh()->tags()->orderBy('name')->pluck('name')->all()
        );

        $this->assertSame(['coverage.excluded'], $excludedDomain->fresh()->tags()->pluck('name')->all());
    }

    public function test_dry_run_does_not_mutate_tags(): void
    {
        config()->set('domain_monitor.coverage_tags', [
            'manual_exclusion_tag' => ['name' => 'coverage.excluded'],
            'tags' => [
                'required' => ['name' => 'coverage.required'],
                'complete' => ['name' => 'coverage.complete'],
                'gap' => ['name' => 'coverage.gap'],
            ],
        ]);

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

        $this->artisan('coverage:sync-tags', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('domain_tags', 0);
        $this->assertSame([], $domain->fresh()->tags()->pluck('name')->all());
    }
}
