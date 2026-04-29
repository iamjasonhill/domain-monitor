<?php

namespace Tests\Feature;

use App\Livewire\AutomationCoverageQueue;
use App\Models\Domain;
use App\Models\DomainSeoBaseline;
use App\Models\PropertyAnalyticsSource;
use App\Models\PropertyRepository;
use App\Models\SearchConsoleCoverageStatus;
use App\Models\User;
use App\Models\WebProperty;
use App\Models\WebPropertyDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use Tests\TestCase;

class AutomationCoverageQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_automation_coverage_queue(): void
    {
        $user = User::factory()->create();

        $needsController = $this->makeProperty('no-controller.example.au', 'No Controller');

        $needsGa4 = $this->makeProperty('no-ga4.example.au', 'No GA4');
        $this->attachRepository($needsGa4);

        $ga4Provisioning = $this->makeProperty('ga4-provisioning.example.au', 'GA4 Provisioning');
        $this->attachRepository($ga4Provisioning);
        $this->attachGa4($ga4Provisioning, null, 'planned', 'provisioning');

        $prefixProperty = $this->makeProperty('prefix.example.au', 'Prefix Only');
        $this->attachRepository($prefixProperty);
        $prefixSource = $this->attachGa4($prefixProperty, 'G-PREFIX001');
        $this->attachCoverage($prefixProperty, $prefixSource, 'url_prefix', now()->subDay()->toDateString());

        $needsOnboarding = $this->makeProperty('needs-onboarding.example.au', 'Needs Onboarding');
        $this->attachRepository($needsOnboarding);
        $needsOnboardingSource = $this->attachGa4($needsOnboarding, 'G-ONBOARD001');
        $this->attachCoverage($needsOnboarding, $needsOnboardingSource, 'domain_property', null);

        $staleProperty = $this->makeProperty('stale.example.au', 'Stale Import');
        $this->attachRepository($staleProperty);
        $staleSource = $this->attachGa4($staleProperty, 'G-STALE0001');
        $this->attachCoverage($staleProperty, $staleSource, 'domain_property', now()->subDays(10)->toDateString());

        $needsBaseline = $this->makeProperty('baseline.example.au', 'Needs Baseline');
        $this->attachRepository($needsBaseline);
        $needsBaselineSource = $this->attachGa4($needsBaseline, 'G-BASELINE1');
        $this->attachCoverage($needsBaseline, $needsBaselineSource, 'domain_property', now()->subDay()->toDateString());

        $csvPending = $this->makeProperty('csv-pending.example.au', 'CSV Pending');
        $this->attachRepository($csvPending);
        $csvPendingSource = $this->attachGa4($csvPending, 'G-CSVPEND01');
        $this->attachCoverage($csvPending, $csvPendingSource, 'domain_property', now()->subDay()->toDateString());
        $this->attachBaseline($csvPending, $csvPendingSource, 'matomo_api');

        $complete = $this->makeProperty('complete.example.au', 'Complete Site');
        $this->attachRepository($complete);
        $completeSource = $this->attachGa4($complete, 'G-COMPLETE1');
        $this->attachCoverage($complete, $completeSource, 'domain_property', now()->subDay()->toDateString());
        $this->attachBaseline($complete, $completeSource, 'matomo_plus_manual_csv');

        $excludedDomain = Domain::factory()->create([
            'domain' => 'parked.example.au',
            'is_active' => true,
            'platform' => 'Parked',
        ]);
        $excludedProperty = WebProperty::factory()->create([
            'slug' => 'parked-site',
            'name' => 'Parked Site',
            'status' => 'active',
            'property_type' => 'domain_asset',
            'primary_domain_id' => $excludedDomain->id,
        ]);
        WebPropertyDomain::create([
            'web_property_id' => $excludedProperty->id,
            'domain_id' => $excludedDomain->id,
            'usage_type' => 'primary',
            'is_canonical' => true,
        ]);

        $response = $this->actingAs($user)->get('/automation-coverage');

        $response->assertOk();
        $response->assertSee('Automation Coverage');
        $response->assertSee('No Controller');
        $response->assertSee('No GA4');
        $response->assertSee('GA4 Provisioning');
        $response->assertSee('Prefix Only');
        $response->assertSee('Needs Onboarding');
        $response->assertSee('Stale Import');
        $response->assertSee('Needs Baseline');
        $response->assertSee('CSV Pending');
        $response->assertSee('Complete Site');
        $response->assertSee('Parked Site');
        $response->assertSee('Manual CSV Pending');

        $this->assertSame('needs_baseline_sync', $needsBaseline->fresh()->automationCoverageSummary()['status']);

        Livewire::test(AutomationCoverageQueue::class)
            ->assertViewHas('stats', function (array $stats): bool {
                return $stats['required'] === 9
                    && $stats['needs_controller'] === 1
                    && $stats['needs_ga4_sync'] === 1
                    && $stats['ga4_provisioning'] === 1
                    && $stats['ga4_attention'] === 0
                    && $stats['needs_search_console_mapping'] === 1
                    && $stats['needs_onboarding'] === 1
                    && $stats['import_stale'] === 1
                    && $stats['needs_baseline_sync'] === 1
                    && $stats['manual_csv_pending'] === 1
                    && $stats['complete'] === 1
                    && $stats['excluded'] === 1;
            })
            ->assertViewHas('needsBaselineSync', function (Collection $items): bool {
                return (bool) $items->first(fn (array $item): bool => $item['property']->name === 'Needs Baseline');
            })
            ->assertViewHas('manualCsvPending', function (Collection $items): bool {
                return (bool) $items->first(fn (array $item): bool => $item['property']->name === 'CSV Pending');
            })
            ->assertViewHas('complete', function (Collection $items): bool {
                return (bool) $items->first(fn (array $item): bool => $item['property']->name === 'Complete Site');
            });
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

    private function attachGa4(
        WebProperty $property,
        ?string $measurementId,
        string $status = 'active',
        ?string $provisioningState = 'switch_ready'
    ): PropertyAnalyticsSource {
        return PropertyAnalyticsSource::create([
            'web_property_id' => $property->id,
            'provider' => 'ga4',
            'external_id' => $measurementId ?? 'planned-'.$property->slug,
            'external_name' => $property->name,
            'is_primary' => false,
            'status' => $status,
            'provider_config' => [
                'measurement_id' => $measurementId,
                'provisioning_state' => $provisioningState,
                'switch_ready' => $measurementId !== null,
                'source_system' => 'MM-Google',
            ],
        ]);
    }

    private function attachCoverage(WebProperty $property, PropertyAnalyticsSource $source, string $mappingState, ?string $latestMetricDate): void
    {
        SearchConsoleCoverageStatus::create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'source_provider' => 'mm-google',
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

    private function attachBaseline(WebProperty $property, PropertyAnalyticsSource $source, string $importMethod): void
    {
        DomainSeoBaseline::create([
            'domain_id' => $property->primary_domain_id,
            'web_property_id' => $property->id,
            'property_analytics_source_id' => $source->id,
            'baseline_type' => 'manual_checkpoint',
            'captured_at' => now()->subDay(),
            'source_provider' => 'matomo',
            'matomo_site_id' => $source->external_id,
            'search_console_property_uri' => 'sc-domain:'.$property->primaryDomainName(),
            'search_type' => 'web',
            'import_method' => $importMethod,
            'clicks' => 10,
            'impressions' => 100,
            'ctr' => 0.1,
            'average_position' => 12.5,
        ]);
    }
}
